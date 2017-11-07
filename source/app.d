import vibe.data.json;
import vibe.http.websockets;
import vibe.core.file;
import vibe.core.path;
import vibe.core.core;
import vibe.core.log;
import vibe.stream.tls;
import vibe.http.router;
import vibe.core.sync : ManualEvent, createManualEvent;
import std.functional : toDelegate;
import std.conv : to, text;
import course;

enum datadir = "/opt/ohq/logs/"; // should exist and contain sessions/ 
// add files for each legal course (e.g., cs1110.log) to datadir

Course[string] courses;
shared string[string] session_key;

void trace(T...)(T args) {
//    import vibe.core.file;
//    appendToFile(datadir~"debug.log", text(args, '\n'));
}

void trackSessions() {
trace(`-> trackSessions()`);
    DirectoryWatcher sessions = watchDirectory(datadir ~ "sessions");
    DirectoryChange[] changes;
    while(sessions.readChanges(changes))
        foreach(change; changes) {
            if (change.type == DirectoryChangeType.modified)
                session_key[change.path.head.toString] = readFileUTF8(change.path);
        }
trace(`<- trackSessions()`);
}

size_t fuzzNum(size_t t) {
    if (t < 20) return t;
    if (t < 100) return ((t+5)/10)*10;
    return ((t+50)/100)*100;
}

bool isOpen() {
    import std.datetime;
    auto now = Clock.currTime;
    if  (   now.dayOfWeek == DayOfWeek.fri
        ||  now.dayOfWeek == DayOfWeek.sat
        ) return false;
    return (now.hour >= 15) && (now.hour < 21);
}


void userSession(scope WebSocket socket) {
    // validate the session for authentication
    // retrieve the appropriate course
    // verify enrollment
    // redirect to TA or Student handler
    
    if (!socket.waitForData) return;
trace(`-> userSession(socket)`);
    Json auth;
    try { auth = parseJsonString(socket.receiveText); }
    catch (Exception ex) { 
        socket.send(`{"type":"error","message":"failed to authenticate"}`); 
trace(`<- userSession(socket)`);
        return; 
    }
    string user = auth["user"].get!string,
        token = auth["token"].get!string,
        course = auth["course"].get!string;

    if (user !in session_key || session_key[user] != token) {
        socket.send(serializeToJsonString(
            ["type":"reauthenticate"
            ,"message":(user in session_key ? session_key[user][9..$] : "")
            ]));
trace(`<- userSession(socket)`);
        return;
    }
    if (course !in courses) {
        string dest = datadir ~ course ~ `.log`;
        auto clean = Path(dest); clean.normalize;
        if (clean.toString == dest && existsFile(clean)) {
            courses[course] = new Course(dest);
            logInfo(text(course, ": ", courses[course].tas.length, " TAs: ", courses[course].tas.keys()));
            logInfo(text(course, ": ", courses[course].students.length, " students: ", courses[course].students.keys()));
        }
        else {
            socket.send(serializeToJsonString(
                ["type":"error"
                ,"message":"invalid course: "~course
                ]));
trace(`<- userSession(socket)`);
            return;
        }
    }
    Course c = courses[course];
    if (user in c.tas)
        taSession(c, c.tas[user], socket);
    else if (user in c.students)
        studentSession(c, c.students[user], socket);
    else
        socket.send(serializeToJsonString(
            ["type":"error"
            ,"message":user ~ " is not enrolled in "~course
            ]));
trace(`<- userSession(socket)`);
}

void taSession(Course c, TA t, scope WebSocket socket) {
trace(`-> taSession(`,c.logfile,`, `, t.id, `)`);
    Status status = Status.lurk;
    size_t position = size_t.max;
    size_t tacount = size_t.max;
    
    auto writer = runTask({
        while(socket.connected) {
            // logInfo("TA "~t.id~" got event");
            bool resend = (t.status != status);
            status = t.status;
            auto tmp = c.hands.length + c.line.length;
            if (tmp != position) { position = tmp; resend = true; }
            
            if (c.ta_online.length != tacount) {
                version(none) { // Json(string[]) fails
                    socket.send(serializeToJsonString([
                            "type":Json("ta-set"),
                            "crowd":Json(c.ta_online),
                        ]));
                } else {
                    auto msg = `{"type":"ta-set","tas":[`;
                    bool comma = false;
                    foreach(tan; c.ta_online) {
                        if (comma) msg ~= `,`;
                        msg ~= `"` ~ tan ~ `"`;
                        comma = true;
                    }
                    socket.send(msg ~ `]}`);
                }
                tacount = c.ta_online.length;
            }
            
            if (resend)
                final switch(status) {
                    case Status.lurk:
                        socket.send(serializeToJsonString([
                            "type":Json("watch"),
                            "crowd":Json(position),
                            // should we send the line too?
                        ]));
                        break;
                    case Status.help:
                        auto h = t.history[$-1];
                        socket.send(serializeToJsonString([
                            "type":Json("assist"),
                            "id":Json(h.s.id),
                            "name":Json(h.s.name),
                            "what":Json(h.task),
                            "where":Json(h.loc),
                            "crowd":Json(position),
                        ]));
                        break;
                    case Status.hand: // TAs cannot raise their hand
                        logError("TA "~t.name~" ("~t.id~") has status \"hand\"");
                        break;
                    case Status.line: // TAs cannot get in line
                        logError("TA "~t.name~" ("~t.id~") has status \"line\"");
                        break;
                }
            c.event.wait;
        }
    });
    
    c.ta_arrive(t);
    
    while(socket.waitForData) {
        auto message = socket.receiveText;
        // logInfo("TA "~t.id~" sent message " ~ message);
        try {
            auto data = message.parseJsonString;
            switch(data["req"].get!string) {
                case "help":
                    if (!c.helpFirst(t))
                        socket.send(`{"type":"error","message":"no students to help"}`);
                    break;
                case "unhelp":
                    if (!c.unhelp(t))
                        socket.send(`{"type":"error","message":"no students to stop helping"}`);
                    break;
                case "resolve":
                    if (!c.resolve(t, data["notes"].get!string))
                        socket.send(`{"type":"error","message":"no students to finish helping"}`);
                    break;
                case "history":
                    Json[] helps = new Json[t.history.length]; // allocate space
                    helps.length = 0;
                    foreach_reverse(h; t.history) {
                        if (h.fin > 0)
                            helps ~= Json([
                                "request":Json(h.req),
                                "help":Json(h.hlp),
                                "finish":Json(h.fin),
                                "id":Json(h.s.id),
                                "name":Json(h.s.name),
                                "what":Json(h.task),
                                "where":Json(h.loc),
                            ]);
                    }
                    socket.send(serializeToJsonString(
                        ["type":Json("ta-history")
                        ,"events":Json(helps)
                        ]));
                    break;
                default:
                    socket.send(serializeToJsonString(
                        ["type":"error"
                        ,"message":"cannot parse "~message
                        ]));
            }
        } catch (JSONException ex) {
            socket.send(serializeToJsonString(
                ["type":"error"
                ,"message":"exception parsing "~message
                ]));
        }
    }

    c.ta_depart(t);

    c.event.emit;
    writer.join;
trace(`<- taSession(`,c.logfile,`, `, t.id, `)`);
}

void studentSession(Course c, Student s, scope WebSocket socket) {
    if (!isOpen) {
        socket.send(serializeToJsonString(
            ["type":"error"
            ,"message":"Office hours are currently closed"
            ]));
        return;
    }

trace(`-> studentSession(`,c.logfile,`, `, s.id, `)`);

    Status status = Status.lurk;
    size_t position = size_t.max;
    
    auto writer = runTask({
        while(socket.connected) {
            if (!isOpen) {
                socket.send(serializeToJsonString(
                    ["type":"error"
                    ,"message":"Office hours are currently closed"
                    ]));
                socket.close();
                break;
            }
            // logInfo("Student "~s.id~" got event");
            bool resend = (s.status != status);
            status = s.status;
            final switch(status) {
                case Status.lurk: goto case;
                case Status.help: goto case;
                case Status.hand:
                    auto tmp = fuzzNum(c.hands.length + c.line.length);
                    if (tmp != position) { position = tmp; resend = true; }
                    break;
                case Status.line:
                    auto tmp = c.line.indexOf(s.history[$-1]);
                    if (tmp != position) { position = tmp; resend = true; }
                    break;
            }
            if (resend)
                final switch(status) {
                    case Status.lurk:
                        socket.send(serializeToJsonString([
                            "type":Json("lurk"),
                            "crowd":Json(position),
                        ]));
                        break;
                    case Status.help:
                        socket.send(serializeToJsonString([
                            "type":Json("help"),
                            "by":Json(s.history[$-1].t.name),
                            "crowd":Json(position),
                        ]));
                        break;
                    case Status.hand:
                        socket.send(serializeToJsonString([
                            "type":Json("hand"),
                            "crowd":Json(position),
                        ]));
                        break;
                    case Status.line:
                        socket.send(serializeToJsonString([
                            "type":Json("line"),
                            "index":Json(position),
                        ]));
                        break;
                }
            c.event.wait;
        }
    });
    
    while(socket.waitForData) {
        if (!isOpen) {
            socket.send(serializeToJsonString(
                ["type":"error"
                ,"message":"Office hours are currently closed"
                ]));
            socket.close();
            break;
        }
        auto message = socket.receiveText;
        // logInfo("Student "~s.id~" sent message " ~ message);
        try {
            auto data = message.parseJsonString;
            switch(data["req"].get!string) {
                case "request":
                    if (!c.request(s, data["where"].get!string, data["what"].get!string))
                        socket.send(`{"type":"error","message":"unable to process duplicate help request"}`);
                    break;
                case "update": // FIXME: currently not logged or reacted to...
                    if (s.status == Status.lurk || s.status == Status.help)
                        socket.send(`{"type":"error","message":"can only edit pending help requests"}`);
                    else {
                        if ("where" in data) s.history[$-1].loc = data["where"].get!string;
                        if ("what" in data) s.history[$-1].task = data["what"].get!string;
                    }
                    break;
                case "retract":
                    if (!c.retract(s))
                        socket.send(`{"type":"error","message":"can only retract pending requests"}`);
                    break;
                case "history":
                    Json[] helps = new Json[s.history.length]; // allocate space
                    helps.length = 0;
                    foreach_reverse(h; s.history) {
                        if (h.fin > 0)
                            helps ~= Json([
                                "request":Json(h.req),
                                "help":Json(h.hlp),
                                "finish":Json(h.fin),
                                "ta":Json(h.t is null ? "none" : h.t.name),
                            ]);
                    }
                    socket.send(serializeToJsonString(
                        ["type":Json("history")
                        ,"events":Json(helps)
                        ]));
                    break;
                default:
                    socket.send(serializeToJsonString(
                        ["type":"error"
                        ,"message":"cannot parse "~message
                        ]));
            }
        } catch (JSONException ex) {
            socket.send(serializeToJsonString(
                ["type":"error"
                ,"message":"exception parsing "~message
                ]));
        }
    }
    
    c.event.emit;
    writer.join;
trace(`<- studentSession(`,c.logfile,`, `, s.id, `)`);

}




shared static this() {
    auto settings = new HTTPServerSettings;
    settings.port = 1111;
    settings.hostName = "archimedes.cs.virginia.edu";
    settings.bindAddresses = ["::1", "127.0.0.1", "128.143.63.34"];
    settings.tlsContext = createTLSContext(TLSContextKind.server);
    settings.tlsContext.useCertificateChainFile("server.cer");
    settings.tlsContext.usePrivateKeyFile("server-pk8.key");
    // openssl crashed (see dmesg) but boson can't read the key files 
    // dub.json "subConfigurations": {"vibe-d:tls":"botan"},
    // gives botan.utils.exceptn.DecodingError@../../zf14/lat7h/.dub/packages/botan-1.12.9/botan/source/botan/pubkey/pkcs8.d(274): Invalid argument: Decoding error: PKCS #8 private key decoding failed: Invalid argument: Decoding error: PKCS #8: Unsupported format: PKCS#1 RSA Private Key file
    // fixed with openssl pkcs8 -in server.key -out server-pk8.key -topk8 -nocrypt
    // then the moment it negotiates contact, we get a segfault


    auto router = new URLRouter;
    router.get("/ws", handleWebSockets(&userSession));

    runTask(toDelegate(&trackSessions));
    import core.time : minutes;
    runTask({
        while (true) {
            sleep(10.minutes);
trace(`-> bookkeeping`);
            auto now = stamp;
            auto hour_ago = now - 60*60;
            foreach(n,c; courses)
                foreach(i,t; c.tas)
                    if (t.status == Status.help && t.history[$-1].hlp < hour_ago)
                        c.resolve(t, "runaway session");
            if (!isOpen)
                foreach(n,c; courses)
                    foreach(i,s; c.students)
                        if (s.status != Status.lurk)
                            c.close(s);
                
trace(`<- bookkeeping`);
        }
    });
    trace("==================================================================");
    listenHTTP(settings, router);
}