import vibe.data.json;
import vibe.core.file;
import vibe.core.log;
import vibe.core.sync : ManualEvent, createSharedManualEvent;
import std.conv : text, to;


uint stamp(uint when = 0) {
    import std.datetime : Clock;
    return cast(typeof(return))(when ? when : Clock.currTime.toUnixTime);
}

enum Status { lurk, hand, line, help, report }
final class Help {
    uint req, hlp, fin;
    string task, task_category, loc, notes;
    Student s;
    TA t;
    
    ulong priority() { return s.priority; }
    
    Json ta_json() {
        return Json([
            "request":Json(req),
            "help":Json(hlp),
            "finish":Json(fin),
            "id":Json(s.id),
            "name":Json(s.name),
            "what":Json(task),
            "where":Json(loc),
        ]);
    }
}
final class Student {
    string id, name;
    Help[] history;
    Status status;
    uint lastHelped() {
        foreach_reverse(h; history)
            if (h.hlp && h.fin)
                return h.hlp;
        return 0;
    }
    uint lastRequested() {
        foreach_reverse(h; history)
            if (h.hlp || !h.fin)
                return h.req;
        return 0;
    }
    ulong priority() {
        ulong ans = lastHelped;
        // consider: ans = ((ans-5*3600)/86400)*86400 to truncate to day granularity (UTC-0500)
        // consider: capping ans to some fixed number of days ago
        ans <<= 32;
        ans |= lastRequested;
        return ans;
    }
    Help request(string where, string what, uint when = 0) {
        if (status != Status.lurk) {
            logInfo("duplicate request? status = " ~ to!string(status));
            return null;
        }
        Help h = new Help;
        h.req = stamp(when);
        h.task = what; h.loc = where;
        h.s = this;
        history ~= h;
        status = Status.hand;
        logInfo("Student request " ~ id);
        return h;
    }
    Help retract(uint when = 0) {
        if (status != Status.hand && status != Status.line) return null;
        Help h = history[$-1];
        h.fin = stamp(when);
        h.notes = "retracted";
        status = Status.lurk;
        if (h.t !is null) {
            h.t.student_id_to_active_help.remove(id);
            h.t.recompute_status();
        }
        logInfo("Student retract " ~ id);
        return h;
    }
    Help close(uint when = 0) {
        if (status != Status.hand && status != Status.line) return null;
        Help h = history[$-1];
        h.fin = stamp(when);
        h.notes = "OH closed";
        if (h.t !is null) {
            h.t.student_id_to_active_help.remove(id);
            h.t.recompute_status();
        }
        logInfo("Student close " ~ id);
        status = Status.lurk;
        return h;
    }
    bool report(string notes, string comments, uint when = 0) {
        // currently, reports are logged but not stored in memory; if we want them in memory, that would change here (probably)
        if (status != Status.report) return false;
        status = Status.lurk;
        return true;
    }
}
final class TA {
    string id, name;
    Help[] history;
    int[string] student_id_to_active_help;
    Status status;

    void recompute_status() {
        if (student_id_to_active_help.length == 0) {
            status = Status.lurk; 
        } else {
            status = Status.help;
        }
    }  
    
    bool help(Help h, uint when = 0) {
        // if (status != Status.lurk || h.hlp || h.fin) return false;
        history ~= h;
        h.t = this;
        h.hlp = stamp(when);
        h.s.status = Status.help;
        status = Status.help;
        student_id_to_active_help[h.s.id] = cast(int)(history.length - 1);
        return true;
    }
    Help unhelp(Student s) {
        if (!(s.id in student_id_to_active_help)) {
            return null;
        }
        Help h = s.history[$-1];
        if (h.t != this) {
            return null;
        }
        // don't remove from history; let them see who they attempted but failed to help
        // h.t = null;
        h.s.status = Status.hand;
        h.hlp = 0;
        student_id_to_active_help.remove(s.id);
        recompute_status();
        return h;
    }
    bool resolveHelp(Help h, string notes, uint when = 0) {
        h.notes = notes;
        h.fin = stamp(when);
        student_id_to_active_help.remove(h.s.id);
        recompute_status();
        
        import std.string : indexOf;
        if (h.notes.indexOf("absent") >= 0)
            h.s.status = Status.lurk; // skip reporting
        else
            h.s.status = Status.report;
        return true;
    }

    bool resolveStudent(Student s, string notes, uint when = 0) {
        foreach_reverse (ref item; history) {
            if (item.fin) continue;
            if (item.s.id == s.id) {
                return resolveHelp(item, notes, when);
            }
        }
        return false;
    }
    bool resolveLast(string notes, uint when = 0) {
        foreach_reverse (ref item; history) {
            if (item.fin) continue;
            return resolveHelp(item, notes, when);
        }
        return false;
    }
}
final class Broadcast {
    string from, text;
    uint posted;
    uint showUntil;
    this(string id, string text, uint duration, uint posted) {
        this.from = id;
        this.text = text;
        this.posted = posted;
        this.showUntil = posted + duration;
    }
    Json msg() const {
        return Json([
            "from":Json(from),
            "message":Json(text),
            "posted":Json(posted),
            "expires":Json(showUntil),
        ]);
    }
}

final class Course {
    TA[string] tas;
    Student[string] students;
    bool softClosed;
    
    import std.container.binaryheap2;
    BinaryHeap!(Help[], "a.priority > b.priority") hands;
    Queue!Help line;
    string[] ta_online;
    Broadcast[] broadcasts;
    int[string] task_count;
    
    string logfile;
    
    shared ManualEvent event;
    
    this(string logfile) {
        softClosed = false;
        this.logfile = logfile;
        hands.acquire(new Help[0]);
        line = new Queue!Help;
        
        event = createSharedManualEvent;

        if (existsFile(logfile)) {
            logInfo("reading "~logfile);
            auto f = readFileUTF8(logfile);
            try {
                while(f.length > 1) {
                    Json data = f.parseJson;
                    // logInfo("record "~data.toString);
                    try {
                        switch(data["action"].get!string) {
                            case "student":
                                addStudent(
                                    data["id"].get!string, 
                                    "name" in data ? data["name"].get!string : null,
                                    false);
                                break;
                            case "ta":
                                addTA(
                                    data["id"].get!string, 
                                    "name" in data ? data["name"].get!string : null,
                                    false);
                                break;
                            case "request":
                                request(
                                    students[data["student"].get!string],
                                    data["where"].get!string,
                                    data["what"].get!string,
                                    data["when"].get!uint
                                );
                                fillLine;
                                break;
                            case "retract":
                                retract(
                                    students[data["student"].get!string],
                                    data["when"].get!uint
                                );
                                fillLine;
                                break;
                            case "close":
                                close(
                                    students[data["student"].get!string],
                                    data["when"].get!uint
                                );
                                fillLine;
                                break;
                            case "help":
                                help(
                                    tas[data["ta"].get!string],
                                    students[data["student"].get!string].history[$-1],
                                    data["when"].get!uint
                                );
                                fillLine;
                                break;
                            case "unhelp":
                                unhelp(
                                    tas[data["ta"].get!string],
                                    students[data["student"].get!string],
                                    data["when"].get!uint
                                );
                                break;
                            case "resolve":
                                resolveStudent(
                                    tas[data["ta"].get!string],
                                    students[data["student"].get!string],
                                    data["notes"].get!string,
                                    data["when"].get!uint
                                );
                                break;
                            case "report":
                                report(
                                    students[data["student"].get!string],
                                    tas[data["ta"].get!string],
                                    data["notes"].get!string,
                                    data["comments"].get!string,
                                    data["when"].get!uint
                                );
                                break;
                            case "arrive": // just a record keeping extra
                                break;
                            case "depart": // just a record keeping extra
                                break;
                            case "broadcast": 
                                addBroadcast(
                                    data["from"].get!string,
                                    data["text"].get!string,
                                    data["duration"].get!uint,
                                    data["posted"].get!uint,
                                    false
                                );
                                break;
                            case "softclose":
                                softClose(false);
                            case "softopen":
                                softOpen(false);
                            default:
                                logError("Unexpected log entry " ~ data.toString);
                        }
                    } catch (JSONException ex) {
                        logError(text("Unexpected log entry ", data.toString, ": ", ex));
                    }
                }
            } catch(JSONException ex) {
                logError("End of log " ~ text(ex));
            }
        }
    }

    void softClose(bool log=true) {
        foreach (h; line.toList) {
            hands.insert(h);
            h.s.status = Status.hand;
            line.remove(h);
        }
        softClosed = true;
        if (log) {
            appendToFile(logfile, serializeToJsonString(["action":"softclose"])~'\n');
        }
        event.emit;
    }
    
    void softOpen(bool log=true) {
        softClosed = false;
        fillLine();
        if (log) {
            appendToFile(logfile, serializeToJsonString(["action":"softopen"])~'\n');
        }
        event.emit;
    }

    void fillLine() {
        // TO DO: make the line length customizable
        // TO DO: add time constraints (line only fills when queue is open)
        while (!softClosed && !hands.empty && (line.length < 3 || line.length*3 < hands.length)) {
            hands.front.s.status = Status.line;
            line.add(hands.front);
            hands.popFront;
        }
    }

    Json waiting_json() {
        Json[] waiters;
        int line_index = 1;
        foreach (h; line.toList) {
            Json h_json = h.ta_json();
            h_json["line"] = line_index;
            line_index += 1;
            h_json["priority"] = h.priority;
            waiters ~= h_json;
        }
        foreach (h; hands.dup) {
            Json h_json = h.ta_json();
            h_json["line"] = null;
            h_json["priority"] = h.priority;
            waiters ~= h_json;
        }
        return Json(waiters);
    }
    
    Json tasks_json() {
        Json[string] ans;
        foreach(k,v; task_count) if (v > 0) ans[k] = Json(v);
        return Json(ans);
    }
    
    void addTA(string id, string name, bool log=true) {
        log &= (id !in tas || (name && name != tas[id].name));
        if (id in students) students.remove(id);
        if (id !in tas) tas[id] = new TA;
        tas[id].id = id;
        if (name) tas[id].name = name;
        if (log) appendToFile(logfile, serializeToJsonString([
            "action":"ta",
            "id":id,
            "name":name,
        ])~'\n');
    }
    void addStudent(string id, string name, bool log=true) {
        log &= (id !in students || (name && name != students[id].name));
        if (id in tas) tas.remove(id);
        if (id !in students) students[id] = new Student;
        students[id].id = id;
        if (name) students[id].name = name;
        if (log) appendToFile(logfile, serializeToJsonString([
            "action":"student",
            "id":id,
            "name":name,
        ])~'\n');
    }
    bool addBroadcast(string id, string text, uint duration, uint posted=0, bool log=true) {
        if (duration == 0) return false;
        if (id !in tas) return false;
        if (posted == 0) posted = stamp;
        if (uint.max - duration <= posted) return false;
        if (log) {
            appendToFile(logfile, serializeToJsonString([
                "action":Json("broadcast"),
                "from":Json(id),
                "text":Json(text),
                "posted":Json(posted),
                "duration":Json(duration),
            ])~'\n');
        }
        auto now = stamp;
        if (posted + duration <= now+30) return false;
        size_t i = 0, j = 0;
        while(j < broadcasts.length) {
            if (broadcasts[j].showUntil <= now) {
                j+=1;
            } else {
                broadcasts[i] = broadcasts[j];
                i+=1;
                j+=1;
            }
        }
        broadcasts.length = i+1;
        broadcasts[i] = new Broadcast(tas[id].name, text, duration, posted);
        event.emit;
        return true;
    }
    
    
    void uploadRoster(Path)(Path rosterFileName) {
        import collab_roster;
        scope participants = readCollabRoster(rosterFileName.toString);
        foreach(k,v; participants)
            if (v[`role`] == `Student` || v[`role`] == `Waitlisted Student`)
                addStudent(k, v[`name`], true);
            else
                addTA(k, v[`name`], true);
    }
    
    
    bool ta_arrive(TA whom) {
        ta_online ~= whom.name;
        appendToFile(logfile, serializeToJsonString([
            "action":Json("arrive"),
            "ta":Json(whom.id),
            "when":Json(stamp),
        ])~'\n');
        event.emit;
        return true;
    }
    bool ta_depart(TA whom) {
        size_t i=0;
        while(i < ta_online.length && ta_online[i] != whom.name) i+=1;
        if (i == ta_online.length) return false;
        foreach(j; i+1..ta_online.length) ta_online[j-1] = ta_online[j];
        ta_online.length = ta_online.length - 1;
        appendToFile(logfile, serializeToJsonString([
            "action":Json("depart"),
            "ta":Json(whom.id),
            "when":Json(stamp),
        ])~'\n');
        event.emit;
        return true;
    }
    
    
    /// TA action wrappers (forwards to TA class, logs, and manages queue)
    bool help(TA from, Help h, uint when = 0) {
        try {
            if (from.help(h, when)) {
                line.remove(h);
                hands.remove(h);
                fillLine();
                task_count[h.task] -= 1;
                if (!when) {
                    appendToFile(logfile, serializeToJsonString([
                        "action":Json("help"),
                        "ta":Json(from.id),
                        "student":Json(h.s.id),
                        "when":Json(h.hlp),
                    ])~'\n');
                    event.emit;
                }
                return true;
            } else return false;
        } catch (Exception ex) { logException(ex, "exception in help"); return false; }
    }
    bool helpStudent(TA from, Student s, uint when = 0) {
        try {
            logInfo(from.id ~ " to help " ~ s.id);
            if (!line.empty) {
                Help h;
                foreach(i; 0..line.length) { // skip ones previously returned to line, if possible
                    h = line[i];
                    if (h.s.id == s.id) {
                        return help(from, h, when);
                    }
                }
            }
            foreach (h; hands.dup) {
                logInfo("checking " ~ h.s.id);
                if (h.s.id == s.id) {
                    return help(from, h, when);
                }
            }
            return false;
        } catch(Exception ex) { logException(ex, "exception in helpStudent"); return false; }
    }
    bool helpFirst(TA from, uint when = 0) {
        try {
            if (!line.empty) {
                Help h;
                foreach(i; 0..line.length) { // skip ones previously returned to line, if possible
                    h = line[i];
                    logInfo(text("help consideration ", i, ": h.t is ", h.t));
                    if (h.t != from) break;
                }
                if (h.t == this) h = line.front;
                return help(from, h, when);
            }
            return false;
        } catch(Exception ex) { logException(ex, "exception in helpFirst"); return false; }
    }
    /// ditto
    bool unhelp(TA from, Student s, uint when = 0) {
        try {
            Help h = from.unhelp(s);
            if (h !is null) {
                version(all) { // return to front of line
                    line.addInFront(h);
                    h.s.status = Status.line;
                    task_count[h.task] += 1;
                } else { // return to crowd
                    hands.insert(h);
                    h.s.status = Status.hand;
                    fillLine();
                    task_count[h.task] += 1;
                }
                if (!when) {
                    appendToFile(logfile, serializeToJsonString([
                        "action":Json("unhelp"),
                        "ta":Json(from.id),
                        "student":Json(h.s.id), // extraneous but nice for some other reports
                        "when":Json(stamp),
                    ])~'\n');
                    event.emit;
                }
                return true;
            } else return false;
        } catch(Exception ex) { logException(ex, "exception in unhelp"); return false; }
    }
    
    bool resolveStudent(TA from, Student s, string notes, uint when = 0) {
        try {
            if (from.resolveStudent(s, notes, when)) {
                Help h = s.history[$-1];
                h.s.status = Status.lurk;
                if (!when) {
                    appendToFile(logfile, serializeToJsonString([
                        "action":Json("resolve"),
                        "ta":Json(from.id),
                        "student":Json(s.id), // extraneous but nice for some other reports
                        "notes":Json(notes),
                        "when":Json(h.fin),
                    ])~'\n');
                    event.emit;
                }
                return true;
            } else return false;
        } catch(Exception ex) { logException(ex, "exception in resolve"); return false; }
    }
    
    /// Student action wrappers (forwards to TA class, logs, and manages queue)
    bool request(Student from, string where, string what, uint when = 0) {
        try {
            Help h = from.request(where, what, when);
            if (h !is null) {
                hands.insert(h);
                task_count[h.task] += 1;
                h.s.status = Status.hand;
                fillLine();
                if (!when) {
                    appendToFile(logfile, serializeToJsonString([
                        "action":Json("request"),
                        "student":Json(from.id),
                        "where":Json(where),
                        "what":Json(what),
                        "when":Json(h.req),
                    ])~'\n');
                    event.emit;
                }
                return true;
            } else {
                logInfo("request ignored? student " ~ from.id);
                return false;
            }
        } catch(Exception ex) { logException(ex, "exception in request"); return false; }
    }
    /// ditto
    bool retract(Student from, uint when = 0) {
        try {
            bool hand = from.status == Status.hand;
            Help h = from.retract(when);
            if (h !is null) {
                if (hand) hands.remove(h);
                else line.remove(h);
                task_count[h.task] -= 1;
                h.s.status = Status.lurk;
                fillLine();
                if (!when) {
                    appendToFile(logfile, serializeToJsonString([
                        "action":Json("retract"),
                        "student":Json(from.id),
                        "when":Json(stamp),
                    ])~'\n');
                    event.emit;
                }
                return true;
            } else return false;
        } catch(Exception ex) { logException(ex, "exception in retract"); return false; }
    }
    /// ditto
    bool close(Student from, uint when = 0) {
        try {
            bool hand = from.status == Status.hand;
            Help h = from.close(when);
            if (h !is null) {
                if (hand) hands.remove(h);
                else line.remove(h);
                task_count[h.task] -= 1;
                h.s.status = Status.lurk;
                fillLine();
                if (!when) {
                    appendToFile(logfile, serializeToJsonString([
                        "action":Json("close"),
                        "student":Json(from.id),
                        "when":Json(stamp),
                    ])~'\n');
                    event.emit;
                }
                return true;
            } else return false;
        } catch(Exception ex) { logException(ex, "exception in close"); return false; }
    }
    /// ditto
    bool report(Student from, TA about, string notes, string comments, uint when = 0) {
        try {
            if (from.report(notes, comments, when)) {
                if (!when) {
                    appendToFile(logfile, serializeToJsonString([
                        "action":Json("report"),
                        "student":Json(from.id),
                        "ta":Json(about ? about.id : "none"),
                        "notes":Json(notes),
                        "comments":Json(comments),
                        "when":Json(stamp),
                    ])~'\n');
                    event.emit;
                }
                return true;
            } else return false;
        } catch(Exception ex) { logException(ex, "exception in report"); return false; }
    }
}


final class Queue(T) {
    T[] backing;
    size_t idx, length;
    this() { backing = new T[16]; }
    bool empty() { return length == 0; }
    T opIndex(size_t i) { return backing[(i+idx)%backing.length]; }
    ref T opIndexAssign(T s, size_t i) { return backing[(i+idx)%backing.length] = s; }
    void add(T s) {
        if (length == backing.length) {
            T[] tmp = new T[backing.length*2];
            if (idx == 0) tmp[0..length] = backing[0..length];
            else {
                tmp[0..length-idx] = backing[idx..length];
                tmp[length-idx..length] = backing[0..idx];
            }
            backing = tmp;
            idx = 0;
        }
        this[length] = s; 
        length += 1; 
    }
    void addInFront(T s) {
        if (length == backing.length) {
            T[] tmp = new T[backing.length*2];
            if (idx == 0) tmp[0..length] = backing[0..length];
            else {
                tmp[0..length-idx] = backing[idx..length];
                tmp[length-idx..length] = backing[0..idx];
            }
            backing = tmp;
            idx = 0;
        }
        idx = (idx+backing.length-1)%backing.length;
        backing[idx] = s;
        length += 1; 
    }
    size_t indexOf(T v) {
        foreach(i; 0..length)
            if (this[i] == v) return i;
        return size_t.max;
    }
    void popFront() in { assert(!empty); } body {
        backing[idx] = T.init;
        idx += 1; idx %= backing.length;
        length -= 1;
    }
    T front() in { assert(!empty); } body { return backing[idx]; /+ faster than this[0] +/}
    T back() in { assert(!empty); } body { return this[length-1]; }
    void remove(T val) {
        if (empty) return;
        if (val == front) popFront;
        else
            foreach(i; 0..length)
                if (this[i] == val) {
                    foreach(j; i..length-1)
                        this[j] = this[j+1];
                    length -= 1;
                    this[length] = T.init;
                    return;
                }
    }
    T[] toList() {
        T[] ans = new T[length];
        foreach(i; 0..length) ans[i] = this[i];
        return ans;
    }
    override string toString() {
        import std.conv;
        return to!string(toList);
    }
    
}

version(none) {
    void main() {
        import std.stdio, std.conv, std.format;
        
        Course c = new Course("cs1110.log");
        c.addTA("ta1", "TA 1", false);
        c.addTA("ta2", "TA 2", false);
        foreach(i; 0..50) {
            c.addStudent(text('s',i+1), text("Student ",i+1), false);
        }
        c.request(c.students["s12"], "home", "hard things", 12);
        c.request(c.students["s20"], "home", "hard things", 20);
        c.helpFirst(c.tas["ta1"], 100);
        c.resolve(c.tas["ta1"], "felt sad", 108);
        foreach(i; 0..50) {
            c.request(c.students[text('s',i+1)], "somewhere", "problems", i+10);
        }
        writeln("Hands: ", c.hands.length);
        writeln("Line: ", c.line.length);
        c.retract(c.students[`s38`]);
        writeln("Hands: ", c.hands.length);
        writeln("Line: ", c.line.length);
        uint now = 1000;
        while(c.line.length > 0) {
            writeln("Hands: ", c.hands.length,"; \tLine: ", c.line.length, "; \tFront: ", (c.line.empty ? "" : c.line.front.s.name));
            if (now%17 < 10) c.unhelp(c.tas[now&1 ? "ta1" : "ta2"], now);
            else c.resolve(c.tas[now&1 ? "ta1" : "ta2"], "fixed", now);
            c.helpFirst(c.tas[now&1 ? "ta1" : "ta2"], now+1);
            now += 3;
        }
    }
}
