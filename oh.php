﻿<!DOCTYPE html>
<html>
<head>
    <title>CS 3130 Office Hours</title>
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
        #waiting table td {
            border: 1px solid black;
        }
    </style>
    <script type="text/javascript">//<!--
'use strict';


<?php /** Authentication: uses netbadge for php but internal tokens for websockets */
$user = $_SERVER['PHP_AUTH_USER'];
$tas = array(
    "cr4bd",
);
if (in_array($user, $tas)  && $_GET['user']) $user=$_GET['user'];

$token_file = "/opt/ohq-cr4bd/logs/sessions/$user";
if (file_exists($token_file) && filemtime($token_file) > time() - 60) { 
    $token = file_get_contents($token_file);
} else {
    $token = bin2hex(openssl_random_pseudo_bytes(4)) . " " . date(DATE_ISO8601);
    file_put_contents($token_file, "$token");
}

?>
var socket;
var user = "<?=$user;?>";
var token = "<?=$token;?>";
var loaded_at = new Date().getTime();

/** Configuration: class name in OHQ */
var course = 'cs3130';

/** Configuration: displayed course name */
var courseName = 'CS 3130';

/** Configuration: help options */
var helpInfo = [
    {
        "name": "what",
        "kind": "text",
        "label": "Problem description: ",
        "ta_label": "description",
        "mandatory": true,
    },
    {
        "name": "location",
        "kind": "text",
        "label": "Location (in-person OH only)",
        "ta_label": "location",
        "persist": true,
        "mandatory": false,
    },
    {
        "name": "public",
        "kind": "boolean",
        "label": "Problem be discussed publicly? (remote OH only)",
        "ta_label": "public?",
        "default": false,
    },
    {
        "name": "discord",
        "kind": "text",
        "label": "Discord name (remote OH only): ",
        "ta_label": "discord",
        "persist": true,
        "mandatory": false,
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
    if (t == 0) {
        return "never";
    }
    var d = new Date(t*1000);
    //console.log([t, d]);
    var n = new Date();
    var days = (n.getTime() - d.getTime())/1000;
    if (days < 24*60*60) return d.toTimeString().substring(0, 5); 
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

function renderStudentRow(row, person, type) {
    row.replaceChildren();
    if (type == "waiting") {
        if (person.line) {
            row.insertCell().replaceChildren(
                document.createTextNode(person.line)
            )
        } else {
            row.insertCell().replaceChildren(
                document.createTextNode("---")
            )
        }
        row.insertCell().replaceChildren(
            document.createTextNode(prettydate(person.priority / Math.pow(2, 32)))
        );
        row.insertCell().replaceChildren(
            document.createTextNode(prettydate(person.request))
        );
    }
    row.insertCell().replaceChildren(
        document.createTextNode(`${person.student_name} (${person.student})`)
    );
    var img = document.createElement("img");
    img.setAttribute("src", `../picture.php?user=${person.student}`);
    img.setAttribute("width", `100`);
    row.insertCell().replaceChildren(img);
    for (var item of helpInfo) {
        row.insertCell().replaceChildren(
            document.createTextNode(person['request-info'][item.name])
        );
    }
    if (type == "helping" || type == "other-helping") {
        row.insertCell().replaceChildren(
            document.createTextNode(prettydate(person.help))
        );
    }
    if (type == "helping") {
        row.insertCell().innerHTML = 
            `<input type="button" value="finished helping" onclick="showfb('${person.student}')" id="feedbackshower-${person.student}">`;
        row.insertCell().innerHTML = 
            `<input type="button" value="return to queue" onclick="unhelp('${person.student}')" id="mainunhelp-${person.student}">`;
    } else if (type == "other-helping") {
        row.insertCell().replaceChildren(
            document.createTextNode(person.ta)
        );
        row.insertCell().innerHTML = `<input type="button" value="Return to queue" onclick="forceUnhelp('${person.student}')">`;
    } else if (type == "waiting") {
        row.insertCell().innerHTML = `<input type="button" value="help" onclick="helpStudent('${person.student}')">`;
        row.insertCell().innerHTML = `<details><summary>...</summary><input type="button" value="dequeue w/o helping" onclick="forceRetract('${person.student}')"></details>`;
    }
}

function renderStudentHeader(row, type) {
    row.replaceChildren();
    if (type == "waiting") {
        row.insertCell().textContent = 'pos';
        row.insertCell().textContent = 'last';
        row.insertCell().textContent = 'since';
    }
    row.insertCell().textContent = 'name';
    row.insertCell().textContent = 'img';
    for (var item of helpInfo) {
        row.insertCell().textContent = item.ta_label;
    }
    if (type == "helping" || type == "other-helping") {
        row.insertCell().textContent = "since";
    }
    if (type == "other-helping") {
        row.insertCell().textContent = "by";
    }
}

function renderStudentList(tbl, lst, type) {
    tbl.replaceChildren();
    var header = tbl.createTHead();
    renderStudentHeader(header.insertRow(), type);
    var body = tbl.createTBody();
    for (var person of lst) {
        var row = body.insertRow()
        renderStudentRow(row, person, type);

        if (type == "helping") {
            var feedback_id = `feedbacktable-${person.student}`;
            var old_feedback = document.getElementById(feedback_id);
            if (old_feedback != null) {
                body.insertRow().appendChild(old_feedback);
                if (old_feedback.getAttribute("style") == "") {
                    document.getElementById(`feedbackshower-${person.student}`).setAttribute("style", "display:none");
                }
            } else {
                var feedback_tbl = document.createElement("table");
                feedback_tbl.setAttribute("style", "border-collapse: collapse");
                for(var k in ta2student) {
                    feedback_tbl.insertRow().innerHTML =`<td><input type="checkbox" value="${k}"></td><td> ${ta2student[k]}</td></tr>`;
                }
                var cell = body.insertRow().insertCell();
                cell.parentElement.setAttribute("id", feedback_id);
                cell.parentElement.setAttribute("style", "display:none");
                cell.setAttribute("colspan", row.children.length);
                var button_div = document.createElement("div");
                button_div.innerHTML = `
                <input type="button" value="finished helping" onclick="resolve('${person.student}')"/>
                <input type="button" value="continue helping" onclick="unshowfb('${person.student}')"/>
                `;
                cell.replaceChildren(feedback_tbl, button_div);
            }
        }
    }
}

function setOtherHelping(elem, data) {
    var filtered_data = [];
    for (const help of data.all_helped) {
        if (help.ta == user)
            continue;
        console.log(`found ${help}`);
        filtered_data.push(help);
    }
    if (filtered_data.length == 0) {
        elem.setAttribute("class", "nohelp");
        elem.replaceChildren(
            document.createTextNode("No students being helped.")
        );
    } else {
        var tbl = document.createElement("table");
        console.log(`filtered_data = ${filtered_data}`);
        renderStudentList(tbl, filtered_data, 'other-helping');
        elem.replaceChildren(tbl);
    }
}

function setHelping(elem, data) {
    if (data.helps.length > 0) {
        var tbl = document.createElement("table");
        renderStudentList(tbl, data.helps, 'helping');
        var you_are = document.createElement("p");
        you_are.textContent = `You are helping ${data.helps.length} students:`;
        elem.replaceChildren(you_are, tbl);
    } else {
        elem.replaceChildren(
            document.createTextNode("You are not helping any students.")
        );
    }
}

function setWaiting(elem, data) {
    if (data.crowd > 0) {
        var tbl = document.createElement("table");
        renderStudentList(tbl, data.waiting, 'waiting');
        elem.replaceChildren(tbl);
    } else {
        elem.replaceChildren(
            document.createTextNode("No students waiting.")
        );
    }
}

function make_ta_announce_form() {
    var announce_form = document.getElementById("announceform");
    announce_form.setAttribute('style', '')
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
        document.body.class = 'empty'
    } else if (data.helps.length > 0) {
        document.body.style.backgroundColor = '#dad0dd';
        document.body.class = 'helping'
    } else {
        document.body.style.backgroundColor = '#ffff00';
        document.body.class = 'waiters'
    }
    var give_help_form = document.getElementById('givehelpform');
    give_help_form.setAttribute('style', '');
    document.getElementById("helponebutton").disabled = (data.crowd == 0);
    var waiting = document.getElementById('waiting')
    waiting.setAttribute('style', '');
    setWaiting(waiting, data);
    var helping = document.getElementById('helping')
    setHelping(helping, data);
    var other_helping = document.getElementById('otherhelping');
    setOtherHelping(other_helping, data);
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
    var wrapper = document.getElementById("wrapper");
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
            wrapper.setAttribute("class", "student");
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
            wrapper.setAttribute("class", "student");
            was_getting_help = false;
            helping.innerHTML = ('<p>You are ' + (data.index+1) + ' in line for help.<hr>' +
                                 '<p>Revise or retract your request:');
            help_form.setAttribute('class', 'status-wait');
        } else if (kind == "hand") {
            wrapper.setAttribute("class", "student");
            was_getting_help = false;
            helping.innerHTML = ('<p>You are one of ' + data.crowd + ' waiting for help.<hr>' +
                                 '<p>Revise or retract your request:');
            help_form.setAttribute('class', 'status-wait');
        } else if (kind == "help") {
            wrapper.setAttribute("class", "student");
            if (!was_getting_help) {
                notifyGettingHelp();
            }
            was_getting_help = true;
            help_form.setAttribute('class', 'status-help');
            helping.innerHTML = '<p>'+data.by+' is helping you.</p>\
            <p>There are currently '+data.crowd+' people waiting for help</p>';
//            <input type="button" value="View your help history" onclick="history()"/>';
        } else if (kind == "history") { // FIXME: use id=history
            wrapper.setAttribute("class", "student");
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
            wrapper.setAttribute("class", "student");
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
            helping.innerHTML = html.join('');
            
/////////////////////////////// The TA Messages ///////////////////////////////
        } else if (kind == "watch") {
            wrapper.setAttribute("class", "ta");
            if (data.crowd == 0) {
                was_empty_oh = true;
            } else if (was_empty_oh) {
                was_empty_oh = false;
                notifyNonEmptyOH();
            }
            data.helps = [];
            make_ta_contents(data);
        } else if (kind == "assist") {
            wrapper.setAttribute("class", "ta");
            was_empty_oh = false;
            make_ta_contents(data);
        } else if (kind == "ta-history") {
            wrapper.setAttribute("class", "ta");
            var historywrapper = document.getElementById('historywrapper');
            historywrapper.setAttribute('style', '');
            historywrapper.setAttribute('open', 'open');
            var container = document.getElementById('history');
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
                row.insertCell().appendChild(document.createTextNode(d.student_name));
                row.insertCell().appendChild(document.createTextNode(d.student));
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
            wrapper.setAttribute("class", "disconnected student");
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
    document.getElementById('mainunhelp-' + student_id).setAttribute('style', 'display:none;');
    document.getElementById('feedbacktable-' + student_id).setAttribute('style', '');
}

function unshowfb(student_id) {
    console.log(`unshowfb(${student_id})`);
    document.getElementById('feedbacktable-' + student_id).setAttribute('style', 'display:none;');
    document.getElementById('mainunhelp-' + student_id).setAttribute('style', '');
    document.getElementById('feedbackshower-' + student_id).setAttribute('style', '');
}

function helpStudent(id) {
    socket.send('{"req":"help","student":"' + id + '"}');
}

function forceRetract(id) {
    if (window.confirm(`Really dequeue student ${id} without helping them?`)) {
        socket.send('{"req":"retract-other","student":"' + id + '"}');
    }
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
function forceUnhelp(student_id) {
    socket.send('{"req":"unhelp-other","student":"' + student_id +'"}');
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
        playDing();
    } else {
        soundEnabled = false;
    }
}

function notifyGettingHelp() {
    if (notificationsEnabled) {
        var notification = new Notification(courseName + " Office Hours", {
            body: "A TA should be helping you shortly."
        });
    }
    playDing();
}

function notifyNonEmptyOH() {
    if (notificationsEnabled) {
        var notification = new Notification(courseName + " Office Hours", {
            body: "A student is asking for help.",
            actions: [ {title: 'Help a student', action: 'helpOne'}, {title: 'Close', action: 'close'} ],
        });
    }
    playDing();
}


    //--></script>
    <style>
        #wrapper { 
            padding:1em; border-radius:1em; background:white;
        }

        #wrapper.disconnected {
            background:#fdd;
        }
        #wrapper.ta #gethelpform { display:none; }
        #wrapper.student #givehelpform { display:none; }
        #wrapper.student #waiting { display:none; }
        #wrapper.student #waitingwrapper { display:none; }
        #wrapper.student #otherhelping { display:none; }
        #wrapper.student #otherhelpingwrapper { display:none; }
        #wrapper.student #announceform { display:none; }
        #wrapper.student #openform{ display:none; }
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
    <div id="wrapper" class="disconnected student">
        <div id="broadcasts"></div>
        <div>This is the queue for CS 3130 office hours.</div>
        <div id="helping"></div>
        <div id="waitsummary"></div>
        <div id="givehelpform">
            <input type="hidden" name="req" value="help">
            <input type="button" value="Help one of them" onclick="sendForm('help')" id="helponebutton">
            <input type="button" value="View your help history" onclick="history()">
        </div>
        <details id="waitingwrapper" open=open>
        <summary>waiting students</summary>
        <div id="waiting"></div>
        </details>
        <details id="otherhelpingwrapper">
        <summary>students being helped by others</summary>
        <div id="otherhelping"></div>
        </details>
        <div id="gethelpform"></div>
        <details id="announceform">
        <summary>Make announcements</summary>
        <p>Announcement text:</br><textarea id="to-send"></textarea><br/>Show for <input type="text" id="show-minutes" value="5" size="4"/> minutes <input type="button" value="post announcement" onclick="broadcastAnnouncement()"/></p>
        </details>
        <div id ="openform"><input type="button" value="soft-close [order students by help time]" onclick="softClose()"><input type="button" value="soft-open [give top students number]" onclick="softOpen()"></div>
        <details id="historywrapper" style="display:none">
        <summary>help history</summary>
        <div id="history"></div>
        </details>
        <div id="misc"></div>
        <pre id="timer">(client-server status log)</pre>
        <div id="notificationprompt">
            <div>
            <input type="checkbox" onclick='changeNotifications()' name="notificationCheckbox" autocomplete="off"
             id="notificationCheckbox"> <label for="notificationCheckbox">enable desktop notifications</label></div>
            <div>
            <input type="checkbox" onclick='changeSound()' name="withSoundCheckbox" autocomplete="off"
             id="withSoundCheckbox"> <label for="withSoundCheckbox">enable sound</label>
             </div>
            <audio style="display:none" id="dingAudio">
                <source src="ding.opus" type="audio/ogg; codecs=opus">
                <source src="ding.mp3" type="audio/mpeg">
            </audio> <!-- FIXME -->
        </div>
    </div>
</body>
</html>
<?php

?>
