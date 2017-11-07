import vibe.data.json;
import vibe.core.file;
import vibe.core.log;
import vibe.core.sync : ManualEvent, createManualEvent;
import std.conv : text;


uint stamp(uint when = 0) {
    import std.datetime : Clock;
    return cast(typeof(return))(when ? when : Clock.currTime.toUnixTime);
}

enum Status { lurk, hand, line, help }
final class Help {
    uint req, hlp, fin;
    string task, loc, notes;
    Student s;
    TA t;
    
    ulong priority() { return s.priority; }
}
final class Student {
    string id, name;
    Help[] history;
    Status status;
    uint lastHelped() {
        foreach_reverse(h; history)
            if (h.hlp && h.fin)
                return h.fin;
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
        if (status != Status.lurk) return null;
        Help h = new Help;
        h.req = stamp(when);
        h.task = what; h.loc = where;
        h.s = this;
        history ~= h;
        status = Status.hand;
        return h;
    }
    Help retract(uint when = 0) {
        if (status != Status.hand && status != Status.line) return null;
        Help h = history[$-1];
        h.fin = stamp(when);
        h.notes = "retracted";
        status = Status.lurk;
        return h;
    }
    Help close(uint when = 0) {
        if (status != Status.hand && status != Status.line) return null;
        Help h = history[$-1];
        h.fin = stamp(when);
        h.notes = "OH closed";
        status = Status.lurk;
        return h;
    }
}
final class TA {
    string id, name;
    Help[] history;
    Status status;
    bool help(Help h, uint when = 0) {
        if (status != Status.lurk || h.hlp || h.fin) return false;
        history ~= h;
        h.t = this;
        h.hlp = stamp(when);
        h.s.status = Status.help;
        status = Status.help;
        return true;
    }
    Help unhelp() {
        if (status != Status.help || history.length == 0 || history[$-1].t != this || history[$-1].fin) return null;
        Help h = history[$-1];
        // don't remove from history; let them see who they attempted but failed to help
        // h.t = null;
        status = Status.lurk;
        h.s.status = Status.hand;
        h.hlp = 0;
        return h;
    }
    bool resolve(string notes, uint when = 0) {
        if (status != Status.help || history.length == 0 || history[$-1].t != this || history[$-1].fin) return false;
        Help h = history[$-1];
        h.notes = notes;
        h.fin = stamp(when);
        status = Status.lurk;
        h.s.status = Status.lurk;
        return true;
    }
}
final class Course {
    TA[string] tas;
    Student[string] students;
    
    import std.container.binaryheap2;
    BinaryHeap!(Help[], "a.priority > b.priority") hands;
    Queue!Help line;
    string[] ta_online;
    
    string logfile;
    
    ManualEvent event;
    
    this(string logfile) {
        this.logfile = logfile;
        hands.acquire(new Help[0]);
        line = new Queue!Help;
        
        event = createManualEvent;

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
                                    data["when"].get!uint
                                );
                                break;
                            case "resolve":
                                resolve(
                                    tas[data["ta"].get!string],
                                    data["notes"].get!string,
                                    data["when"].get!uint
                                );
                                break;
                            default:
                                logError("Unexpected log entry " ~ data.toString);
                        }
                    } catch (JSONException ex) {
                        logError("Unexpected log entry " ~ data.toString);
                    }
                }
            } catch(JSONException ex) {
                logError("End of log " ~ text(ex));
            }
        }
    }
    void fillLine() {
        // TO DO: make the line length customizable
        // TO DO: add time constraints (line only fills when queue is open)
        while (!hands.empty && (line.length < 10 || line.length*2 < hands.length)) {
            hands.front.s.status = Status.line;
            line.add(hands.front);
            hands.popFront;
        }
    }
    
    void addTA(string id, string name, bool log=true) {
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
        if (id !in students) students[id] = new Student;
        students[id].id = id;
        if (name) students[id].name = name;
        if (log) appendToFile(logfile, serializeToJsonString([
            "action":"student",
            "id":id,
            "name":name,
        ])~'\n');
    }
    
    bool ta_arrive(TA whom) {
        ta_online ~= whom.name;
        event.emit;
        return true;
    }
    bool ta_depart(TA whom) {
        size_t i=0;
        while(i < ta_online.length && ta_online[i] != whom.name) i+=1;
        if (i == ta_online.length) return false;
        foreach(j; i+1..ta_online.length) ta_online[j-1] = ta_online[j];
        ta_online.length = ta_online.length - 1;
        event.emit;
        return true;
    }
    
    
    /// TA action wrappers (forwards to TA class, logs, and manages queue)
    bool help(TA from, Help h, uint when = 0) {
        try {
            if (from.help(h, when)) {
                line.remove(h);
                fillLine();
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
        } catch (Exception ex) { import app; app.trace("exception in help: ", ex); return false; }
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
        } catch(Exception ex) { import app; app.trace("exception in helpFirst: ", ex); return false; }
    }
    /// ditto
    bool unhelp(TA from, uint when = 0) {
        try {
            Help h = from.unhelp;
            if (h !is null) {
                version(all) { // return to front of line
                    line.addInFront(h);
                    h.s.status = Status.line;
                } else { // return to crowd
                    hands.insert(h);
                    fillLine();
                }
                if (!when) {
                    appendToFile(logfile, serializeToJsonString([
                        "action":Json("unhelp"),
                        "ta":Json(from.id),
                        "when":Json(stamp),
                    ])~'\n');
                    event.emit;
                }
                return true;
            } else return false;
        } catch(Exception ex) { import app; app.trace("exception in unhelp: ", ex); return false; }
    }
    /// ditto
    bool resolve(TA from, string notes, uint when = 0) {
        try {
            if (from.resolve(notes, when)) {
                if (!when) {
                    appendToFile(logfile, serializeToJsonString([
                        "action":Json("resolve"),
                        "ta":Json(from.id),
                        "notes":Json(notes),
                        "when":Json(from.history[$-1].fin),
                    ])~'\n');
                    event.emit;
                }
                return true;
            } else return false;
        } catch(Exception ex) { import app; app.trace("exception in resolve: ", ex); return false; }
    }
    
    /// Student action wrappers (forwards to TA class, logs, and manages queue)
    bool request(Student from, string where, string what, uint when = 0) {
        try {
            Help h = from.request(where, what, when);
            if (h !is null) {
                hands.insert(h);
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
            } else return false;
        } catch(Exception ex) { import app; app.trace("exception in request: ", ex); return false; }
    }
    /// ditto
    bool retract(Student from, uint when = 0) {
        try {
            bool hand = from.status == Status.hand;
            Help h = from.retract(when);
            if (h !is null) {
                if (hand) hands.remove(h);
                else line.remove(h);
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
        } catch(Exception ex) { import app; app.trace("exception in retract: ", ex); return false; }
    }
    /// ditto
    bool close(Student from, uint when = 0) {
        try {
            bool hand = from.status == Status.hand;
            Help h = from.close(when);
            if (h !is null) {
                if (hand) hands.remove(h);
                else line.remove(h);
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
        } catch(Exception ex) { import app; app.trace("exception in close: ", ex); return false; }
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
