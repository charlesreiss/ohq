import vibe.data.json;
import vibe.http.websockets;
import vibe.core.file;
import vibe.core.path;
import vibe.core.core;
import vibe.core.log;
import vibe.stream.tls;
import vibe.http.router;
import vibe.core.sync : ManualEvent, createManualEvent;
import std.algorithm : startsWith;
import std.functional : toDelegate;
import std.conv : to, text;
import course;

enum name = "ohq-cr4bd-test";
enum datadir = "/opt/" ~ name ~ "/logs/"; // should exist and contain sessions/ 
// add files for each legal course (e.g., cs1110.log) to datadir

Course[string] courses;
shared string[string] session_key;

void trackSessions() {
    logInfo(text("about to track sessions in ", datadir ,"sessions"));
    DirectoryWatcher sessions = watchDirectory(datadir ~ "sessions");
    DirectoryChange[] changes;
    while(sessions.readChanges(changes))
        foreach(change; changes) {
            logInfo(text("detected change in session dir ", change));
            if (change.type == DirectoryChangeType.modified)
                session_key[change.path.head.name] = readFileUTF8(change.path);
        }
}

size_t fuzzNum(size_t t) {
    if (t < 20) return t;
    if (t < 100) return ((t+5)/10)*10;
    return ((t+50)/100)*100;
}

bool isOpen() {
    return true;
    // return false;
    /+
    import std.datetime;
    auto now = Clock.currTime;
    if  (   now.dayOfWeek == DayOfWeek.fri
        ||  now.dayOfWeek == DayOfWeek.sat
        ) return false;
    return (now.hour >= 11) && (now.hour <= 17);
    // +/
}


void userSession(scope WebSocket socket) {
    // validate the session for authentication
    // retrieve the appropriate course
    // verify enrollment
    // redirect to TA or Student handler
    
    scope(exit) socket.close;
    if (!socket.waitForData) return;
    Json auth;
    try { auth = parseJsonString(socket.receiveText); }
    catch (Exception ex) { 
        socket.send(`{"type":"error","message":"failed to authenticate"}`); 
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
        return;
    }
    if (course !in courses) {
        string dest = datadir ~ course ~ `.log`;
        auto clean = NativePath(dest); clean.normalize;
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
            return;
        }
    }
    Course c = courses[course];
    if (user.startsWith("sampleta-") && !(user in c.tas))
        c.addTA(user, user, true);
    if (user.startsWith("sample-") && !(user in c.students))
        c.addStudent(user, user, true);
    if (user in c.tas)
        taSession(c, c.tas[user], socket);
    else if (user in c.students)
        studentSession(c, c.students[user], socket);
    else
        socket.send(serializeToJsonString(
            ["type":"error"
            ,"message":user ~ " is not enrolled in "~course
            ]));
}

void taSession(Course c, TA t, scope WebSocket socket) {
    Status status = Status.lurk;
    size_t position = size_t.max;
    size_t tacount = size_t.max;
    uint last_broadcast = 0;
    bool changed_helping = false;
    
    auto writer = runTask({
        while(socket.connected) {
            // logInfo("TA "~t.id~" got event");
            bool resend = (true || t.status != status || changed_helping);
            changed_helping = false;
            status = t.status;
            auto tmp = c.hands.length + c.line.length;
            if (tmp != position) { position = tmp; resend = true; }
            
            if (c.ta_online.length != tacount) {
                auto msg = `{"type":"ta-set","tas":[`;
                bool comma = false;
                foreach(tan; c.ta_online) {
                    if (comma) msg ~= `,`;
                    msg ~= `"` ~ tan ~ `"`;
                    comma = true;
                }
                socket.send(msg ~ `]}`);
                tacount = c.ta_online.length;
            }
            Json[] alerts;
            foreach(i; 0..c.broadcasts.length) {
                if (c.broadcasts[i].posted > last_broadcast) {
                    alerts ~= c.broadcasts[i].msg;
                    if (i == c.broadcasts.length-1) last_broadcast = c.broadcasts[i].posted;
                    resend = true;
                }
            }
            
            if (resend)
                final switch(status) {
                    case Status.lurk:
                        socket.send(serializeToJsonString([
                            "type":Json("watch"),
                            "crowd":Json(position),
                            "waiting":c.waiting_json,
                            "broadcasts":Json(alerts),
                        ]));
                        break;
                    case Status.help:
                        Json[] helps;
                        foreach (k,v; t.student_id_to_active_help) {
                            auto h = t.history[v];
                            helps ~= h.as_json();
                        }
                        auto h = t.history[$-1];
                        socket.send(serializeToJsonString([
                            "type":Json("assist"),
                            "helps":Json(helps),
                            "crowd":Json(position),
                            "waiting":c.waiting_json,
                            "broadcasts":Json(alerts),
                        ]));
                        break;
                    case Status.hand: // TAs cannot raise their hand
                        logError("TA "~t.name~" ("~t.id~") has status \"hand\"");
                        break;
                    case Status.line: // TAs cannot get in line
                        logError("TA "~t.name~" ("~t.id~") has status \"line\"");
                        break;
                    case Status.report: // TAs cannot report on other TAs
                        logError("TA "~t.name~" ("~t.id~") has status \"report\"");
                        break;
                }
            logInfo("TA "~t.id~" waiting");
            c.event.wait;
            logInfo("TA "~t.id~" done waiting");
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
                    changed_helping = true;
                    if ("student" in data) {
                        if (data["student"].get!string in c.students) {
                            if (!c.helpStudent(t, c.students[data["student"].get!string]))
                                socket.send(`{"type":"error","message":"no students to help"}`);
                        } else {
                            socket.send(`{"type":"error","message":"invalid student to help"}`);
                        }
                    } else {
                        if (!c.helpFirst(t))
                            socket.send(`{"type":"error","message":"no students to help"}`);
                    }
                    break;
                case "unhelp":
                    changed_helping = true;
                    if (!c.unhelp(t, c.students[data["student"].get!string]))
                        socket.send(`{"type":"error","message":"no students to stop helping"}`);
                    c.event.emit;
                    break;
                case "resolve":
                    changed_helping = true;
                    if (("student" in data) != null) {
                        if (!c.resolveStudent(t, c.students[data["student"].get!string], data["notes"].get!string))
                            socket.send(`{"type":"error","message":"not helping `~data["student"].get!string~`"}`);
                    }
                    break;
                case "history":
                    Json[] helps = new Json[t.history.length]; // allocate space
                    helps.length = 0;
                    foreach_reverse(h; t.history) {
                        if (h.fin > 0)
                            helps ~= h.as_json();
                    }
                    socket.send(serializeToJsonString(
                        ["type":Json("ta-history")
                        ,"events":Json(helps)
                        ]));
                    break;
                case "softclose":
                    c.softClose();
                    break;
                case "softopen":
                    c.softOpen();
                    break;
                case "broadcast":
                    if (!c.addBroadcast(t.id, data["message"].get!string, data["seconds"].get!uint))
                        socket.send(`{"type":"error","message":"cannot broadcast such a brief message"}`);
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
            logInfo("\n\n%s\n\n", ex);
        }
    }

    c.ta_depart(t);

    c.event.emit;
    writer.join;
}

void studentSession(Course c, Student s, scope WebSocket socket) {
    if (!isOpen) {
        socket.send(serializeToJsonString(
            ["type":"error"
            ,"message":"Office hours are currently closed"
            ]));
        return;
    }


    Status status = Status.lurk;
    size_t position = size_t.max;
    uint last_broadcast = 0;
    
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
                case Status.report: goto case;
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

            Json[] alerts;
            foreach(i; 0..c.broadcasts.length) {
                if (c.broadcasts[i].posted > last_broadcast) {
                    alerts ~= c.broadcasts[i].msg;
                    if (i == c.broadcasts.length-1) last_broadcast = c.broadcasts[i].posted;
                    resend = true;
                }
            }

            if (resend)
                final switch(status) {
                    case Status.lurk:
                        if (s.history.length == 0) {
                            socket.send(serializeToJsonString([
                                "type":Json("lurk"),
                                "crowd":Json(position),
                                "broadcasts":Json(alerts),
                            ]));
                        } else {
                            socket.send(serializeToJsonString([
                                "type":Json("lurk"),
                                "crowd":Json(position),
                                "broadcasts":Json(alerts),
                                "last-request-info":encodeRequestInfo(s.history[$-1].request_info),
                            ]));
                        }
                        break;
                    case Status.help:
                        socket.send(serializeToJsonString([
                            "type":Json("help"),
                            "by":Json(s.history[$-1].t.name),
                            "crowd":Json(position),
                            "broadcasts":Json(alerts),
                            "last-request-info":encodeRequestInfo(s.history[$-1].request_info),
                        ]));
                        break;
                    case Status.hand:
                        socket.send(serializeToJsonString([
                            "type":Json("hand"),
                            "crowd":Json(position),
                            "broadcasts":Json(alerts),
                            "last-request-info":encodeRequestInfo(s.history[$-1].request_info),
                        ]));
                        break;
                    case Status.line:
                        socket.send(serializeToJsonString([
                            "type":Json("line"),
                            "index":Json(position),
                            "broadcasts":Json(alerts),
                            "last-request-info":encodeRequestInfo(s.history[$-1].request_info),
                        ]));
                        break;
                    case Status.report:
                        if (s.history.length > 0) {
                            auto h = s.history[$-1];
                            if (h.t) {
                                socket.send(serializeToJsonString([
                                    "type":Json("report"),
                                    "when":Json(h.fin),
                                    "ta":Json(h.t.id),
                                    "ta-name":Json(h.t.name),
                                    "broadcasts":Json(alerts),
                                ]));
                            } else goto case Status.lurk;
                        } else goto case Status.lurk;
                        break;
                }
            logInfo("Student "~s.id~" waiting");
            c.event.wait;
            logInfo("Student "~s.id~" done waiting");
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
                    if (!c.request(s, extractRequestInfo(data)))
                        socket.send(`{"type":"error","message":"unable to process duplicate help request"}`);
                    break;
                case "update":
                    if (s.status == Status.lurk || s.status == Status.help) {
                        socket.send(`{"type":"error","message":"can only edit pending help requests"}`);
                    } else {
                        c.updateRequest(s, extractRequestInfo(data));
                    }
                    break;
                case "retract":
                    if (!c.retract(s))
                        socket.send(`{"type":"error","message":"can only retract pending requests"}`);
                    break;
                case "report":
                    if (!c.report(s, s.history.length ? s.history[$-1].t : null, data["notes"].get!string, data["comments"].get!string))
                        socket.send(`{"type":"error","message":"can only report pending requests"}`);
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
            logInfo("\n\n%s\n\n", ex);
        }
    }
    
    c.event.emit;
    writer.join;
}


void uploadRoster(scope HTTPServerRequest req, scope HTTPServerResponse res) {
logInfo("%s", req);
    res.headers["Access-Control-Allow-Origin"] = "https://kytos.cs.virginia.edu";
    
    auto auth = req.form;
logInfo("%s", auth);
logInfo("%s", req.files);
    
    string user = auth["user"],
        token = auth["token"],
        course = auth["course"];

    if (user !in session_key || session_key[user] != token) {
        res.writeBody(serializeToJsonString(
            ["type":"reauthenticate"
            ,"message":(user in session_key ? session_key[user][9..$] : "")
            ]));
        return;
    }
logInfo("valid token");
    Json allowed = parseJsonString(readFileUTF8(datadir~`superusers.json`));
    
    if (user !in allowed) {
        res.writeBody(serializeToJsonString(
            ["type":"error"
            ,"message":"forbidden; please see Luther Tychonievich for permissions"
            ]));
        return;
    }
logInfo("permitted user");
        
    import std.algorithm.searching : canFind;
    import std.array : join;
    if (allowed[user].get!string.canFind(course) || allowed[user].get!string.canFind(`any`)) {
        if (course !in courses) {
            string dest = datadir ~ course ~ `.log`;
            auto clean = NativePath(dest); clean.normalize;
            if (clean.toString != dest) {
                res.writeBody(serializeToJsonString(
                    ["type":"error"
                    ,"message":"illegal course name"
                    ]));
                return;
            }
            if (!existsFile(clean)) {
                writeFileUTF8(clean, serializeToJsonString([
                    `action`:`ta`,
                    `id`:user,
                ])~"\n");
                logInfo(text(user, ` created course `, course));
            }
            courses[course] = new Course(dest);
        }
        Course c = courses[course];
        try {
            auto ot = c.tas.keys();
            auto os = c.students.keys();
            c.uploadRoster(req.files["file"].tempPath);
            auto nt = c.tas.keys();
            writeFile(NativePath("/var/www/html/ohq/.htcourses/"~course~"-staff.csv"), cast(immutable(ubyte)[])join(nt,"\n"));
            auto ns = c.students.keys();
            
            res.writeBody(serializeToJsonString(
                ["type":"success"
                ,"message":text("roster uploaded successfully; added ", 
                    nt.length-ot.length, " staff (",nt.length," total) and ",
                    ns.length-os.length, " students (",ns.length," total)")
                ]));
        } catch (Exception ex) {
            res.writeBody(serializeToJsonString(
                ["type":"error"
                ,"message":"failed to parse roster file (send file to Luther Tychonievich for workaround)"
                ]));
        }
        
    } else {
        res.writeBody(serializeToJsonString(
            ["type":"error"
            ,"message":course~" is not yours; please see Luther Tychonievich if this is incorrect"
        ]));
    }
    
    res.writeBody(`OK`);
}


shared static this() {
    auto settings = new HTTPServerSettings;
    settings.port = 1113;
    settings.hostName = "kytos.cs.virginia.edu";
    settings.bindAddresses = [/+"::1", "127.0.0.1",+/"128.143.67.106"];
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
    router.post("*", &uploadRoster);

    runTask(toDelegate(&trackSessions));
    import core.time : minutes;
    runTask({
        while (true) {
            sleep(10.minutes);
            auto now = stamp;
            auto hour_ago = now - 60*60;
            if (!isOpen)
                foreach(n,c; courses)
                    foreach(i,s; c.students)
                        if (s.status != Status.lurk)
                            c.close(s);
        }
    });
    listenHTTP(settings, router);
}
