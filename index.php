<!DOCTYPE html>
<html>
<head>
    <title>CS4414 Office Hours</title>
    <style type="text/css">
        input#info-what {
            width: 80%;
        }
        #gethelpform.status-help {
            display: none;
        }
        #gethelpform.status-unknown {
            display: none;
        }
        #gethelpform.status-wait #gethelpformsubmitrequest {
            display: none;
        }
        #gethelpform.status-lurk #gethelpformsubmitupdate {
            display: none;
        }
        #gethelpform.status-lurk #gethelpformsubmitretract {
            display: none;
        }
    </style>
    <script type="text/javascript">//<!--
'use strict';


<?php /** Authentication: uses netbadge for php but internal tokens for websockets */
$user = $_SERVER['PHP_AUTH_USER'];
if (($user == "cr4bd" || $user == "kml9pw" || $user == "jth5zs" || $user == "acs3ss" || $user == "ye4pg" || $user == "js7ke" || $user == "skk3gd" || $user == "mtp4be" || $user == "jaa8r" || $user == "yf2ey" || $user == "ww5zf" || $user == "qed4wg" || $user == "aa8qz" || $user == "fas9nw" || $user == "lw2ef")  && $_GET['user']) $user=$_GET['user'];

$token = bin2hex(openssl_random_pseudo_bytes(4)) . " " . date(DATE_ISO8601);
file_put_contents("/opt/ohq-cr4bd/logs/sessions/$user", "$token");
?>
var socket;
var user = "<?=$user;?>";
var token = "<?=$token;?>";
var loaded_at = new Date().getTime();

/** Configuration: class name in OHQ */
var course = 'cs3330';

/** Configuration: displayed course name */
var courseName = 'CS 3330';

/** Configuration: help options */
var helpInfo = [
    {
        "name": "discord",
        "kind": "text",
        "label": "Discord ID: ",
        "ta_label": "discord",
        "persist": true,
        "mandatory": true,
    },
    {
        "name": "what",
        "kind": "text",
        "label": "Problem description: ",
        "ta_label": "description",
        "mandatory": true,
    },
    {
        "name": "public",
        "kind": "boolean",
        "label": "Problem be discussed publicly?",
        "ta_label": "public?",
        "default": false,
    },
];


/** Configuration: lists of feedback options */
var student2ta = {
    "helpful": "Helpful",
    "unhelpful": "Unhelpful",
    "polite": "Polite",
    "rude": "Rude",
    "unhurried": "Took enough time",
    "hurried": "Rushed",
    "listened": "Listened to my questions",
    "condescended": "Was condescending",
    "learning": "Focused on my learning more than on solving my problem",
    "solving": "Focused on solving my problem more than on my learning",
}
var ta2student = {
    "debuging": "Debugging help",
    "conceptual": "Conceptual help",
    "design": "Design help",
    "tech": "Computer/site/systems help",
    "grub": "Wanted answers, not learning",
    "check": "Pre-grading; <q>is this OK</q>",
    "read": "Didn\'t read",
    "rude": "Rude",
    "absent": "Not present",
    "other": "Other",
}

/** UI material */
var words = {
    20:"twenty",
    30:"thirty",
    40:"forty",
    50:"fifty",
    60:"sixty",
    70:"seventy",
    80:"eighty",
    90:"ninty",
    100:"a hundred",
    200:"two hundred",
}

var getHelpStatus = '';

function about(n) { // to match the approximation in the vibe program
    if (n in words) return "around " + words[n];
    if (n > 200) return "several hundred";
    if (n >= 20) return "around "+n;
    else return n;
}

function timedelta(t1, t2) {
    var dt = t2-t1;
    if (dt < 60) return ((dt+0.5)|0) + ' sec';
    dt /= 60;
    if (dt < 60) return ((dt+0.5)|0) + ' min';
    dt /= 60;
    return ((dt+0.5)|0) + ' hr';
}
function prettydate(t) {
    var d = new Date(t*1000);
    //console.log([t, d]);
    var n = new Date();
    var days = (n.getTime() - d.getTime())/1000;
    if (days < 24*60*60) return timedelta(0,days) + ' ago';
    return d.toTimeString().substring(0,5) + '\n' + d.toDateString().substring(0, 10);
}

var helpInfoByName = {};

function initHelpInfoByName() {
    for (const item of helpInfo) {
        helpInfoByName[item['name']] = item;
    }
}

var didInitGetHelpForm;

function initGetHelpForm() {
    if (didInitGetHelpForm) {
        return;
    }
    didInitGetHelpForm = true;
    var outer_div = document.getElementById('gethelpform');
    outer_div.setAttribute('style', '');
    outer_div.innerHTML = '';
    for (const item of helpInfo) {
        var item_div = document.createElement('div');
        item_div.setAttribute('class', 'info-' + item['name']);
        var item_label = document.createElement('label');
        item_label.appendChild(document.createTextNode(item['label']));
        item_label.setAttribute('for', 'info-' + item['name']);
        item_div.appendChild(item_label)
        var item_input = document.createElement('input')
        item_input.setAttribute('id', 'info-' + item['name']);
        item_input.setAttribute('name', 'info-' + item['name']);
        switch (item['kind']) {
            case 'text':
                item_input.setAttribute('type', 'text');
                item_input.setAttribute('value', item['default'] || '')
                break;
            case 'boolean':
                item_input.setAttribute('type', 'checkbox');
                item_input.setAttribute('value', 'true');
                if (item['default']) {
                    item_input.setAttributed('checked', 'checked')
                }
                break;
            default:
                console.log('unknown kind in ' + item);
                break;
        }
        item_div.appendChild(item_input);
        outer_div.appendChild(item_div);
    }
    var submit_div = document.createElement('div');
    var submit_input = document.createElement('input');
    submit_input.setAttribute('id', 'gethelpformsubmitrequest');
    submit_input.setAttribute('type', 'button');
    submit_input.setAttribute('value', 'request help!');
    submit_input.setAttribute('onclick', 'sendForm("request")');
    submit_div.appendChild(submit_input);
    var update_input = document.createElement('input');
    update_input.setAttribute('id', 'gethelpformsubmitupdate');
    update_input.setAttribute('type', 'button');
    update_input.setAttribute('value', 'update request');
    update_input.setAttribute('onclick', 'sendForm("update")');
    submit_div.appendChild(update_input);
    var retract_input = document.createElement('input');
    retract_input.setAttribute('id', 'gethelpformsubmitretract');
    retract_input.setAttribute('type', 'button');
    retract_input.setAttribute('value', 'retract request');
    retract_input.setAttribute('onclick', 'sendForm("retract")');
    submit_div.appendChild(retract_input)
    outer_div.appendChild(submit_div);
}


function getWaitingSummary(data) {
    var no_one = ''; var people = '';
    if (data.helps.length > 0) {
        no_one = 'No one else';
        people = 'other people';
    } else {
        no_one = 'No one';
        people = 'people';
    }
    if (data.crowd == 0) {
        return '<p>' + no_one + ' is waiting for help.</p>';
    } else if (data.crowd == 1) {
        return '<p>there is 1 person waiting for help.</p>';
    } else {
        return '<p>there are '+data.crowd+' ' + people +' waiting for help.</p>';
    }
}
function getWaitingList(data) {
    if (data.crowd == 0) {
        return '<p>No students waiting.</p>'
    } else {
        var html = [];
        html.push('<table>')
        html.push('<thead><tr><th>pos</th><th>name/id</th>');
        for (var item of helpInfo) {
            html.push('<th>' + item.ta_label + '</th>');
        }
        html.push('<th>help</th></tr></thead><tbody>');
        for (var person of data.waiting) {
            if (person.line) {
                html.push('<tr><td class="position">' + person.line + '</td>');
            } else {
                html.push('<tr><td class="position">last helped' + prettydate(person.priority / Math.pow(2, 32)) + '</td>');
            }
            html.push('<td>' + person.student_name + ' (' + person.student + ')' + '</td>');
            for (var item of helpInfo) {
                html.push('<td>' + person['request-info'][item.name] + '</td>');
            }
            html.push('<td><input type="button" value="help this student" onclick="helpStudent(\'' + person.student + '\')"></td>');
        }
        html.push('</tbody></table>')
        return html.join('');
    }
}

function getHelpingList(data) {
    var html = [];
    if (data.helps.length > 0) {
        html.push('<p>You are helping ', data.helps.length, ' students:<table>');
        html.push('<thead><tr><th>name/id</th><th>picture</th>');
        for (var item of helpInfo) {
            html.push('<th>' + item.ta_label + '</th>');
        }
        html.push('<th>finish?</th>');
        html.push('</tr></thead><tbody>');
        var column_count = helpInfo.length + 3;
        for (var help of data.helps) {
            html.push('<tr>');
            html.push('<td>' + help.student_name + ' (' + help.student + ')' + '</td>');
            html.push('<td><img class="float" src="picture.php?user=', help.student, '"/></td>')
            for (var item of helpInfo) {
                html.push('<td>' + help['request-info'][item.name] + '</td>');
            }
            // '<p>Seat: <b>', data.where, '</b></p>',
            html.push(
                '<td><input type="button" value="Finished helping" onclick="showfb(\'', help.student, '\')" id="feedbackshower-', help.student, '"></td>',
            )
            html.push('</tr>');
            html.push(
                '<tr id="feedbacktable-', help.student, '" style="display:none">',
                '<td colspan=' + column_count + '>',
                '<table style="border-collapse: collapse"><tbody>',
            );
            for(var k in ta2student) {
                html.push('<tr><td><input type="checkbox" value="',k,'"></td><td> ',ta2student[k],'</td></tr>')
            }
            html.push(
                '</tbody></table>',
                '<input type="button" value="Finished helping" onclick="resolve(\'', help.student, '\')"/>',
                '<input type="button" value="Return to queue unhelped" onclick="unhelp(\'', help.student, '\')"/>',
                '</td>',
                '</tr>',
            )
        }
        html.push('</ul>');
    } else {
        html.push('<p>You are not helping any students.</p>');
    }
    return html.join('');
}

function make_ta_announce_form() {
    var announce_form = document.getElementById("announceform");
    announce_form.setAttribute('style', '')
    announce_form.innerHTML = '<p>Announcement text:</br><textarea id="to-send"></textarea><br/>Show for <input type="text" id="show-minutes" value="5" size="4"/> minutes <input type="button" value="post announcement" onclick="broadcastAnnouncement()"/></p><p><input type="button" value="soft-close [order students by help time]" onclick="softClose()"><input type="button" value="soft-open [give top students number]" onclick="softOpen()">';
}

function make_ta_contents(data) {
    make_ta_announce_form();
    var wait_summary = document.getElementById('waitsummary');
    wait_summary.innerHTML = getWaitingSummary(data);
    if (data.helps.length > 0) {
        document.title = 'helping ' + data.helps.length + ' student' + (data.helps.length > 1 ? 's' : '');
    } else if (data.crowd == 0) {
        document.title = 'empty office hours';
    } else {
        document.title = data.crowd + ' waiting students';
    }
    if (data.crowd == 0) {
        document.body.style.backgroundColor = '#dad0dd';
    } else {
        document.body.style.backgroundColor = '#ffff00';
    }
    var give_help_form = document.getElementById('givehelpform');
    give_help_form.setAttribute('style', '');
    document.getElementById("helponebutton").disabled = (data.crowd == 0);
    var waiting = document.getElementById('waiting')
    waiting.setAttribute('style', '');
    waiting.innerHTML = getWaitingList(data);
    var helping = document.getElementById('helping')
    helping.innerHTML = getHelpingList(data);
}

function setGetHelpFormFrom(request_info, only_persist) {
    console.log('request-info = ' + request_info);
    for (const item of helpInfo) {
        var the_input = document.getElementById('info-' + item['name'])
        var new_value = undefined;
        if (!only_persist || item['persist']) {
            if (request_info && request_info[item['name']]) {
                new_value = request_info[item['name']];
            }
        }
        if (item['kind'] == 'boolean') {
            if (new_value == 'true')
                the_input.checked = true;
            else
                the_input.checked = false;
        } else if (new_value) {
            the_input.value = new_value;
        } else {
            the_input.value = '';
        }
    }
}

/** main websocket guts... probably needs refactoring */
function connect() {
    setText("connecting "+user+"...");
    initHelpInfoByName();
    var helping = document.getElementById("helping");
    var help_form = document.getElementById("gethelpform");
    var give_help_form = document.getElementById("givehelpform");
    var announce_form = document.getElementById("announceform");
    give_help_form.setAttribute('style', 'display:none;')
    announce_form.setAttribute('style', 'display:none;')
    var set_get_help_form = false;
    initGetHelpForm();
    var was_getting_help = false;
    var was_empty_oh = false;
    help_form.setAttribute('class', 'status-unknown');
    socket = new WebSocket(getBaseURL() + "/ws");
    socket.onopen = function() {
        setText("connected; live updates enabled");
        socket.send(JSON.stringify({user:user, token:token, course:course}));
    }
    socket.onmessage = function(message) {
        console.log("message: " + message.data);
        var data = JSON.parse(message.data);
        var kind = data["type"];
        delete data["."];
        var can_post = '<p>Announcement text:</br><textarea id="to-send"></textarea><br/>Show for <input type="text" id="show-minutes" value="5" size="4"/> minutes <input type="button" value="post announcement" onclick="broadcastAnnouncement()"/></p><p><input type="button" value="soft-close [order students by help time]" onclick="softClose()"><input type="button" value="soft-open [give top students number]" onclick="softOpen()">';
        if ('last-request-info' in data && !set_get_help_form) {
            setGetHelpFormFrom(data['last-request-info'], kind == 'lurk');
            set_get_help_form = true;
        }
        if (data.broadcasts) {
            for(var i=0; i<data.broadcasts.length; i+=1) showBroadcast(data.broadcasts[i]);
            delete data['broadcasts'];
        }
        if (kind == 'error') {
            setText('ERROR: ' + data.message);
            if (data.message.indexOf('currently closed') >= 0) {
                alert("Office hours are currently closed.");
            }

///////////////////////////// The Student Messages /////////////////////////////
        } else if (kind == 'lurk') {
            was_getting_help = false;
            var html = [
                // '<img class="float" src="//archimedes.cs.virginia.edu/StacksStickers.png"/>',
                '<p>There are currently ', about(data.crowd), ' students waiting for help</p>',
            ];
            helping.innerHTML = html.join('');
            if (help_form.class != 'status-lurk') {
                help_form.setAttribute('class', 'status-lurk');
                setGetHelpFormFrom(data['last-request-info'], true);
                set_get_help_form = true;
            }
        } else if (kind == "line") {
            was_getting_help = false;
            helping.innerHTML = ('<p>You are ' + (data.index+1) + ' in line for help.<hr>' +
                                 '<p>Revise or retract your request:');
            help_form.setAttribute('class', 'status-wait');
        } else if (kind == "hand") {
            was_getting_help = false;
            helping.innerHTML = ('<p>You are one of ' + data.crowd + ' waiting for help.<hr>' +
                                 '<p>Revise or retract your request:');
            help_form.setAttribute('class', 'status-wait');
        } else if (kind == "help") {
            if (!was_getting_help) {
                notifyGettingHelp();
            }
            was_getting_help = true;
            help_form.setAttribute('class', 'status-help');
            helping.innerHTML = '<p>'+data.by+' is helping you.</p>\
            <p>There are currently '+data.crowd+' people waiting for help</p>';
//            <input type="button" value="View your help history" onclick="history()"/>';
        } else if (kind == "history") {
            //console.log('history');
            //console.log(data);
            var tab = document.createElement('table');
            tab.appendChild(document.createElement('thead'));
            var row = tab.children[0].insertRow();
            row.insertCell().appendChild(document.createTextNode('Request'));
            row.insertCell().appendChild(document.createTextNode('Wait'));
            row.insertCell().appendChild(document.createTextNode('Duration'));
            row.insertCell().appendChild(document.createTextNode('Helper'));
            tab.appendChild(document.createElement('tbody'));
            for(var i = 0; i < data.events.length; i += 1) {
                row = tab.children[1].insertRow();
                var d = data.events[i];
                row.insertCell().appendChild(document.createTextNode(prettydate(d.request)));
                row.insertCell().appendChild(document.createTextNode(d.help ? timedelta(d.request, d.help) : timedelta(d.request, d.finish)));
                row.insertCell().appendChild(document.createTextNode(d.help ? timedelta(d.help, d.finish) : '—'));
                row.insertCell().appendChild(document.createTextNode(d.ta));
            }
            if (helping.lastElementChild.tagName.toLowerCase() == 'table')
                content.removeChild(content.lastElementChild);
            helping.appendChild(tab);
            //console.log(tab);
        } else if (kind == "report") {
            var html = [
                '<p>Please provide feedback on your recent help from ', data['ta-name'], ':</p>',
                '<table style="border-collapse: collapse"><tbody>',
            ];
            for(var k in student2ta) {
                html.push('<tr><td><input type="checkbox" value="',k,'"></td><td> ',student2ta[k],'</td></tr>')
            }
            html.push(
                '</tbody></table>',
                'Other comments:<br/> <textarea rows="5" cols="40" style="width:100%"></textarea><br/>',
                '<input type="button" value="Submit feedback" onclick="report()"/>',
            );
            help_form.innerHTML = html.join('');
            
/////////////////////////////// The TA Messages ///////////////////////////////
        } else if (kind == "watch") {
            if (data.crowd == 0) {
                was_empty_oh = true;
            } else if (was_empty_oh) {
                was_empty_oh = false;
                notifyNonEmptyOH();
            }
            data.helps = [];
            make_ta_contents(data);
        } else if (kind == "assist") {
            was_empty_oh = false;
            make_ta_contents(data);
        } else if (kind == "ta-history") {
            var container = document.getElementById('history');
            container.setAttribute('style', '');
            var tab = document.createElement('table');
            tab.appendChild(document.createElement('thead'));
            var row = tab.children[0].insertRow();
            row.insertCell().appendChild(document.createTextNode('Help'));
            row.insertCell().appendChild(document.createTextNode('Duration'));
            row.insertCell().appendChild(document.createTextNode('Wait'));
            row.insertCell().appendChild(document.createTextNode('Name'));
            row.insertCell().appendChild(document.createTextNode('ID'));
            row.insertCell().appendChild(document.createTextNode('Picture'));
            row.insertCell().appendChild(document.createTextNode('Task'));
            row.insertCell().appendChild(document.createTextNode('Seat'));
            tab.appendChild(document.createElement('tbody'));
            for(var i = 0; i < data.events.length; i += 1) {
                row = tab.children[1].insertRow();
                var d = data.events[i];
                row.insertCell().appendChild(document.createTextNode(prettydate(d.finish)));
                row.insertCell().appendChild(document.createTextNode(timedelta(d.help, d.finish)));
                row.insertCell().appendChild(document.createTextNode(timedelta(d.request, d.help)));
                row.insertCell().appendChild(document.createTextNode(d.name));
                row.insertCell().appendChild(document.createTextNode(d.id));
                row.insertCell().innerHTML = '<img class="small" src="picture.php?user='+d.id+'"/>';
                row.insertCell().appendChild(document.createTextNode(d.what));
                row.insertCell().appendChild(document.createTextNode(d.where));
            }
            for (var child of container.children) {
                child.remove();
            }
            container.appendChild(tab);
            // console.log(tab);
        } else if (kind == "ta-set") {
            var tas = data.tas.sort().filter(function(el,i,a){return !i||el!=a[i-1];});
            document.getElementById("misc").innerHTML = tas.length + " TA"+(tas.length == 1 ? '' : 's')+" online: <span class='ta'>" + tas.join("</span> <span class='ta'>") + "</span>";
        } else if (kind == "reauthenticate") {
            var now = new Date().getTime();
            if (loaded_at +10*1000 < now) {// at least 10 seconds to avoid refresh frenzy
                window.location.reload(false);
                setText("Unexpected message \""+kind+"\" (please report this to the professor if it stays on the screen)");
            } else {
                setText("connection closed; reload page to make a new connection.");
            }
        } else {
            setText("Unexpected message \""+kind+"\" (please report this to the professor)");
        }
    }
    socket.onclose = function() {
        setText("connection closed; reload page to make a new connection.");
        var now = new Date().getTime();
        if (loaded_at +10*1000 < now) // at least 10 seconds to avoid refresh frenzy
            setTimeout(function(){window.location.reload(false);}, 10);
    }
    socket.onerror = function() {
        setText("error connecting to server");
    }
}

function showfb(student_id) {
    document.getElementById('feedbackshower-' + student_id).setAttribute('style', 'display:none;');
    document.getElementById('feedbacktable-' + student_id).setAttribute('style', '');
}

function helpStudent(id) {
    socket.send('{"req":"help","student":"' + id + '"}');
}

function sendForm(req) {
    var obj = {};
    var info_map_key = 'request-info';
    obj[info_map_key] = {};
    var ins = document.getElementsByTagName('input');
    for(var i=0; i<ins.length; i+=1) {
        console.log('checking ' + ins[i].outerHTML);
        console.log('with value ' + ins[i].value + ' and name ' + ins[i].name);
        if (ins[i].name) {
            if (ins[i].name.startsWith('info-')) {
                var info_name = ins[i].name.substring(5);
                var metadata = helpInfoByName[info_name];
                if (ins[i].type == 'checkbox') {
                    if (ins[i].checked) {
                        obj[info_map_key][info_name] = 'true';
                    } else {
                        obj[info_map_key][info_name] = 'false';
                    }
                } else if (ins[i].value) {
                    obj[info_map_key][info_name] = ins[i].value;
                } else if (req == 'request' && metadata['mandatory']) {
                    alert("Failed to provide "+ metadata['label'])
                    return;
                }
            } else if (ins[i].value) {
                obj[ins[i].name] = ins[i].value;
            }
        }
    }
    obj['req'] = req;
    ins = document.getElementsByTagName('select');
    for(var i=0; i<ins.length; i+=1)
        if (ins[i].name) {
            if (!ins[i].value && req == 'request') {
                alert("Failed to provide "+(
                    ins[i].name == 'where' ? "your location" : 
                    ins[i].name == 'what' ? "your task" : 
                    ins[i].name));
                return;
            } else if (!ins[i].value) {
                continue;
            }
            obj[ins[i].name] = ins[i].value;
        }
    socket.send(JSON.stringify(obj));
}

function resolve(student_id) {
    var message = [];
    var table = document.getElementById('feedbacktable-' + student_id);
    var ins = table.getElementsByTagName('input');
    for(var i=0; i<ins.length; i+=1)
        if (ins[i].checked)
            message.push(ins[i].value);
    console.log('about to resolve') 
    socket.send('{"req":"resolve","student":"' + student_id + '","notes":"'+message+'"}');
}
function report() {
    var message = [];
    var ins = document.getElementsByTagName('input');
    for(var i=0; i<ins.length; i+=1)
        if (ins[i].checked)
            message.push(ins[i].value);
    var comments = document.getElementsByTagName('textarea')[0].value;
    socket.send(JSON.stringify({
        req:"report",
        notes:message.join(','),
        comments:comments
    }));
}
function unhelp(student_id) {
    socket.send('{"req":"unhelp","student":"' + student_id +'"}');
}
function history() {
    socket.send('{"req":"history"}');
}
function closeConnection() {
    socket.close();
    setText("connection closed; reload page to make a new connection.");
}

function setText(text) {
    console.log("text: ", text);
    if (socket && socket.readyState >= socket.CLOSING) {
        text = "(unconnected) "+text;
        document.title = "(unconnected) Office Hours";
    }
    document.getElementById("timer").innerHTML += "\n"+text;
}

function showBroadcast(broadcast) {
    console.log('broadcast', broadcast);
    var now = new Date().getTime()/1000;
    console.log('time left', broadcast.expires - now);
    
    if (broadcast.expires > now) {
        var sent = new Date(broadcast.posted*1000);
        sent = sent.toLocaleString();
        var msg = document.createElement('div');
        msg.setAttribute('class','alert');
        msg.setAttribute('expires', broadcast.expires);
        msg.innerHTML = 
            '<span class="sent-time">'+sent+'</span>' +
            '<span class="announcer">'+broadcast.from+'</span>' +
            '<span class="announcement">'+broadcast.message+'</span>';
        document.getElementById('broadcasts').appendChild(msg);
        setTimeout(expireBroadcasts, 1000*(broadcast.expires-now+1));
    }
}

function expireBroadcasts() {
    var now = new Date().getTime()/1000;
    document.querySelectorAll('.alert').forEach(function(node){
        if (node.getAttribute('expires') < now) node.remove();
    });
}
function softClose() {
    socket.send(JSON.stringify({'req':'softclose'}));
}
function softOpen() {
    socket.send(JSON.stringify({'req':'softopen'}));
}
function broadcastAnnouncement() {
    var text = document.getElementById('to-send').value.trim();
    if (text.length < 1) return;
    var duration = document.getElementById('show-minutes').value * 60;
    if (!(duration > 30)) { document.getElementById('show-minutes').value = 1; return; }
    if (window.confirm('Post the following announcement for '+document.getElementById('show-minutes').value+' minutes? This cannot be edited after posting...\n\n'+text)) {
        socket.send(JSON.stringify({'req':'broadcast','message':text,'seconds':duration}));
    }
}

function getBaseURL() {
    var wsurl = "wss://" + window.location.hostname+':1112' // not ':'+window.location.port
    return wsurl;
}

var notificationsEnabled = false;
var soundEnabled = false;

function enableNotifications() {
    var checkbox = document.getElementById("notificationCheckbox");
    if (!("Notification" in window)) {
        document.alert('Your browser does not support desktop notifications.');
    } else if (Notification.permission === "denied") {
        document.alert('Desktop notification permissions not granted.');
    } else if (Notification.permission === "granted") {
        //
        notificationsEnabled = true;
    } else {
        Notification.requestPermission().then(function (permission) {
            if (permission == "granted") {
                notificationsEnabled = true;
            } else {
                notificationsEnabled = false;
                checkbox.checked = false;
            }
        });
        addEventListener('notificationclick', function (event) {
            event.notification.close();
            if (event.action == 'helpOne') {
                sendForm('help');
            }
        });
    }
}

function playDing() {
    if (soundEnabled) {
        var dingAudio = document.getElementById('dingAudio');
        dingAudio.currentTime = 0;
        dingAudio.play();
    }
}

function enableSound() {
    playDing();
}

function changeNotifications() {
    var checkbox = document.getElementById("notificationCheckbox");
    if (checkbox.checked) {
        enableNotifications();
    } else {
        notificationsEnabled = false;
    }
}

function changeSound() {
    var checkbox = document.getElementById("withSoundCheckbox");
    if (checkbox.checked) {
        soundEnabled = true;
        enableSound();
    } else {
        soundEnabled = false;
    }
}

function notifyGettingHelp() {
    if (enableNotifications) {
        var notification = new Notification(courseName + " Office Hours", {
            body: "A TA should be helping you shortly."
        });
    }
}

function notifyNonEmptyOH() {
    if (enableNotifications) {
        var notification = new Notification(courseName + " Office Hours", {
            body: "A student is asking for help.",
            actions: [ {title: 'Help a student', action: 'helpOne'}, {title: 'Close', action: 'close'} ],
        });
    }
}


    //--></script>
    <style>
        #wrapper { 
            padding:1em; border-radius:1em; background:white;
        }
        body { background: #dad0dd; font-family: sans-serif; }
        pre#timer {
            border: 1px solid grey;
            olor: grey;
        }
        input[type="checkbox"] {
            width:3em; height:3em; display:inline-block;
        }
        input[type="button"] {
            height:3em;
        }
        input, option, select { font-size:100%; }
        td { padding:0.5ex; }
        img.float { float:right; max-width:50%; clear:both; }
        img.small { max-height:5em; }
        thead { font-weight: bold; }
        tr:nth-child(2n) { background-color:#eee; }
        table { border-collapse: collapse; }
        #misc { margin-top:0.5em; }
        #misc .ta { padding: 0.5ex; margin:0.5ex; border-radius:1ex; background: #dad0dd; }
        
        .alert { padding: 1ex; margin: 1ex; border-radius: 1.5ex; background: black; color: white; }
        .alert .announcer:after { content: " announced "; }
        .alert .sent-time:after { content: " "; }
        .alert .announcer { opacity: 0.5; font-size: 70.7%; }
        .alert .sent-time { opacity: 0.5; font-size: 70.7%; }
        .alert .announcement { display: table; margin: auto; }
        
        #to-send { width: 100%; }

        #notificationprompt { font-size: 50%; }
        #notificationprompt input { font-size: 50%; }
    </style>
</head>
<body onLoad="connect()">
    <div id="wrapper">
        <div id="broadcasts"></div>
        <div>This is the queue for CS 3330 office hours. We will be using Discord unless otherwise noted.
             For setting up Discord, see instructions
             <a href="https://www.cs.virginia.edu/~cr4bd/3330/F2020/online.html#oh">here</a>.</div>
        <div id="helping"></div>
        <div id="waitsummary"></div>
        <div id="givehelpform" style="display:none">
            <input type="hidden" name="req" value="help">
            <input type="button" value="Help one of them" onclick="sendForm('help')" id="helponebutton">
            <input type="button" value="View your help history" onclick="history()">
        </div>
        <div id="waiting"></div>
        <div id="gethelpform"></div>
        <div id="announceform"></div>
        <div id="history"></div>
        <div id="misc"></div>
        <pre id="timer">(client-server status log)</pre>
        <div id="notificationprompt">
            <div>
            <input type="checkbox" onclick='changeNotifications()' name="notificationCheckbox"
             id="notificationCheckbox"> <label for="notificationCheckbox">enable desktop notifications</label></div>
            <div>
            <input type="checkbox" onclick='changeSound()' name="withSoundCheckbox"
             id="withSoundCheckbox"> <label for="withSoundCheckbox">enable sound</label>
             </div>
            <audio style="display:none" id="dingAudio">
                <source src="ding.opus" type="audio/ogg; codecs=opus">
                <source src="ding.mp3" type="audio/mpeg">
            </audio> <!-- FIXME -->
        </div>
    </div>
    <datalist id="seats">
    <option value="A1">A1</option>
    <option value="A2">A2</option>
    <option value="A3">A3</option>
    <option value="A4">A4</option>
    <option value="A5">A5</option>
    <option value="A6">A6</option>
    <option value="A7">A7</option>
    <option value="A8">A8</option>
    <option value="A9">A9</option>
    <option value="B1">B1</option>
    <option value="B2">B2</option>
    <option value="B3">B3</option>
    <option value="B4">B4</option>
    <option value="B5">B5</option>
    <option value="B6">B6</option>
    <option value="B7">B7</option>
    <option value="B8">B8</option>
    <option value="B9">B9</option>
    <option value="C1">C1</option>
    <option value="C2">C2</option>
    <option value="C3">C3</option>
    <option value="C4">C4</option>
    <option value="C5">C5</option>
    <option value="C6">C6</option>
    <option value="C7">C7</option>
    <option value="C8">C8</option>
    <option value="C9">C9</option>
    <option value="D1">D1</option>
    <option value="D2">D2</option>
    <option value="D3">D3</option>
    <option value="D4">D4</option>
    <option value="D5">D5</option>
    <option value="D6">D6</option>
    <option value="D7">D7</option>
    <option value="D8">D8</option>
    <option value="D9">D9</option>
    <option value="E1">E1</option>
    <option value="E2">E2</option>
    <option value="E3">E3</option>
    <option value="E4">E4</option>
    <option value="E5">E5</option>
    <option value="E6">E6</option>
    <option value="E7">E7</option>
    <option value="E8">E8</option>
    <option value="E9">E9</option>
    <option value="F1">F1</option>
    <option value="F2">F2</option>
    <option value="F3">F3</option>
    <option value="F4">F4</option>
    <option value="F5">F5</option>
    <option value="F6">F6</option>
    <option value="F7">F7</option>
    <option value="F8">F8</option>
    <option value="F9">F9</option>
    <option value="G1">G1</option>
    <option value="G2">G2</option>
    <option value="G3">G3</option>
    <option value="G4">G4</option>
    <option value="G5">G5</option>
    <option value="G6">G6</option>
    <option value="G7">G7</option>
    <option value="G8">G8</option>
    <option value="G9">G9</option>
    <option value="H1">H1</option>
    <option value="H2">H2</option>
    <option value="H3">H3</option>
    <option value="H4">H4</option>
    <option value="H5">H5</option>
    <option value="H6">H6</option>
    <option value="H7">H7</option>
    <option value="H8">H8</option>
    <option value="H9">H9</option>
    <option value="I1">I1</option>
    <option value="I2">I2</option>
    <option value="I3">I3</option>
    <option value="I4">I4</option>
    <option value="I5">I5</option>
    <option value="I6">I6</option>
    <option value="I7">I7</option>
    <option value="I8">I8</option>
    <option value="I9">I9</option>

    <option value="J1">J1</option>
    <option value="J2">J2</option>
    <option value="J3">J3</option>
    <option value="J4">J4</option>
    <option value="K1">K1</option>
    <option value="K2">K2</option>
    <option value="K3">K3</option>
    <option value="K4">K4</option>
    <option value="L1">L1</option>
    <option value="L2">L2</option>
    <option value="L3">L3</option>
    <option value="L4">L4</option>
    <option value="M1">M1</option>
    <option value="M2">M2</option>
    <option value="M3">M3</option>
    <option value="M4">M4</option>
    <option value="N1">N1</option>
    <option value="N2">N2</option>
    <option value="N3">N3</option>
    <option value="N4">N4</option>
    <option value="O1">O1</option>
    <option value="O2">O2</option>
    <option value="O3">O3</option>
    <option value="O4">O4</option>
    <option value="P1">P1</option>
    <option value="P2">P2</option>
    <option value="P3">P3</option>
    <option value="P4">P4</option>
    <option value="Q1">Q1</option>
    <option value="Q2">Q2</option>
    <option value="Q3">Q3</option>
    <option value="Q4">Q4</option>
    <option value="R1">R1</option>
    <option value="R2">R2</option>
    <option value="R3">R3</option>
    <option value="R4">R4</option>
    <option value="S1">S1</option>
    <option value="S2">S2</option>
    <option value="S3">S3</option>
    <option value="S4">S4</option>
    <option value="T1">T1</option>
    <option value="T2">T2</option>
    <option value="T3">T3</option>
    </datalist>
</body>
</html>
<?php

?>
