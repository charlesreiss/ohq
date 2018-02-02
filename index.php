<!DOCTYPE html>
<html>
<head>
    <title>Office Hours Help</title>
    <script type="text/javascript">//<!--
<?php
$user = $_SERVER['PHP_AUTH_USER'];
if ($user == "lat7h" && $_GET['user']) $user=$_GET['user'];
// if (strpos($_GET['user'], 'student_') === 0) $user = $_GET['user']; // beta only!

$token = bin2hex(openssl_random_pseudo_bytes(4)) . " " . date(DATE_ISO8601);
file_put_contents("/opt/ohq/logs/sessions/$user", "$token");
?>

var socket;
var user = "<?=$user;?>";
var token = "<?=$token;?>";
var loaded_at = new Date().getTime();

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

function connect() {
    setText("connecting "+user+"...");
    var content = document.getElementById("content");
    socket = new WebSocket(getBaseURL() + "/ws");
    socket.onopen = function() {
        setText("connected; live updates enabled");
        socket.send(JSON.stringify({user:user, token:token, course:'cs1110'}));
    }
    socket.onmessage = function(message) {
        console.log("message: " + message.data);
        var data = JSON.parse(message.data);
        var kind = data["type"];
        delete data["."];
        if (kind == 'error') {
            console.log(data.message);
            setText('ERROR: ' + data.message);
            if (data.message.indexOf('currently closed') >= 0) {
                alert("Office hours are currently closed.");
            }

///////////////////////////// The Student Messages /////////////////////////////
        } else if (kind == 'lurk') {
            content.innerHTML = '<img class="float" src="//cs1110.cs.virginia.edu/StacksStickers.png"/><p>There are currently '+about(data.crowd)+' other students waiting for help</p>\
            <input type="hidden" name="req" value="request"/>\
            <p>Location: <input type="text" name="where" list="seats"/> (should be a seat number in Thorton Stacks; see label at your table or map to right)</p>\
            <p>Task: <select name="what">\
                <option value="">(select one)</option>\
                <option value="conceptual">non-homework help</option>\
                <option value="pa01">01 - greeting.py</option>\
                <option value="pa02">02 - nonsense.py</option>\
                <option value="pa03">03 - dating.py</option>\
                <option value="pa04">04 - c2f.py</option>\
                <option value="pa05">05 - maydate.py</option>\
                <option value="pa06">06 - conversion.py</option>\
                <option value="pa07">07 - gpa.py</option>\
                <option value="pa08">08 - bmr.py</option>\
                <option value="pa09">09 - averages.py</option>\
                <option value="pa10">10 - calculator.py</option>\
                <option value="pa11">11 - higher_lower.py</option>\
                <option value="pa12">12 - higher_lower_player.py</option>\
                <option value="pa13">13 - str_redux.py</option>\
                <option value="pa14">14 - map_reduce.py</option>\
                <option value="pa15">15 - credit_card.py</option>\
                <option value="pa16">16 - gradebook.py</option>\
                <option value="pa17">17 - lous_list.py</option>\
                <option value="pa18">18 - spellcheck.py</option>\
                <option value="pa19">19 - flappybird.py</option>\
                <option value="pa20">20 - regexs.py</option>\
                <option value="pa21">21 - salary.py</option>\
                <option value="project">Game Project</option>\
            </select></p>\
            <input type="button" value="Request Help" onclick="sendForm()"/>';
//            <input type="button" value="View your help history" onclick="history()"/>';
        } else if (kind == "line") {
            content.innerHTML = '<p>You are currently number '+(data.index+1)+' in line for getting help</p>\
            <input type="hidden" name="req" value="retract"/>\
            <input type="button" value="Retract your help request" onclick="sendForm()"/>';
//            <input type="button" value="View your help history" onclick="history()"/>';
        } else if (kind == "hand") {
            content.innerHTML = '<p>You are currently one of '+about(data.crowd)+' students waiting for help</p>\
            <input type="hidden" name="req" value="retract"/>\
            <input type="button" value="Retract your help request" onclick="sendForm()"/>';
//            <input type="button" value="View your help history" onclick="history()"/>';
        } else if (kind == "help") {
            content.innerHTML = '<p>'+data.by+' is helping you.</p>\
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
            if (content.lastElementChild.tagName.toLowerCase() == 'table')
                content.removeChild(content.lastElementChild);
            content.appendChild(tab);
            //console.log(tab);
        } else if (kind == "report") {
            content.innerHTML = '<p>Please provide feedback on your recent help from '+data['ta-name']+':'
            + '</p>\
            <table style="border-collapse: collapse"><tbody>\
            <tr><td><input type="checkbox" value="helpful"/></td><td> Helpful</td></tr>\
            <tr><td><input type="checkbox" value="unhelpful"/></td><td> Unhelpful</td></tr>\
            <tr><td><input type="checkbox" value="polite"/></td><td> Polite</td></tr>\
            <tr><td><input type="checkbox" value="rude"/></td><td> Rude</td></tr>\
            <tr><td><input type="checkbox" value="unhurried"/></td><td> Took enough time</td></tr>\
            <tr><td><input type="checkbox" value="hurried"/></td><td> Rushed</td></tr>\
            <tr><td><input type="checkbox" value="listened"/></td><td> Listened to my questions</td></tr>\
            <tr><td><input type="checkbox" value="condescended"/></td><td> Was condescending</td></tr>\
            <tr><td><input type="checkbox" value="learning"/></td><td> Focussed on my learning more than on solving my problem</td></tr>\
            <tr><td><input type="checkbox" value="solving"/></td><td> Focussed on solving my problem more than on my learning</td></tr>\
            </tbody></table>\
            Other comments:<br/> <textarea rows="5" cols="40" style="width:100%"></textarea><br/>\
            <input type="button" value="Submit feedback" onclick="report()"/>\
            ';
            
            
/////////////////////////////// The TA Messages ///////////////////////////////
        } else if (kind == "watch") {
            if (data.crowd == 0) {
                content.innerHTML = '<p>No one is waiting for help.</p>\
                <input type="button" value="View your help history" onclick="history()"/>';
                document.title = 'Empty OHs';
                document.body.style.backgroundColor = '#dad0dd';
            } else {
                content.innerHTML = '<p>There are '+data.crowd+' people waiting for help.</p>\
                <input type="hidden" name="req" value="help"/>\
                <input type="button" value="Help one of them" onclick="sendForm()"/>\
                <input type="button" value="View your help history" onclick="history()"/>';
                document.title = data.crowd+ ' waiting people';
                document.body.style.backgroundColor = '#ffff00';
            }
        } else if (kind == "assist") {
            if (data.crowd == 0) document.body.style.backgroundColor = '#dad0dd';
            else document.body.style.backgroundColor = '#ffff00';
            
            content.innerHTML = '<p>You are helping '+data.name+' ('+data.id+') '
            + '<img class="float" src="picture.php?user='+data.id+'"/>'
            + '</p>\
            <p>Seat: <b>'+data.where+'</b><img class="float" src="//cs1110.cs.virginia.edu/StacksStickers.png"/></p><p>Problem: '+data.what+'</p>\
            <p>There are '+data.crowd+' other people waiting for help.</p>\
            <table style="border-collapse: collapse"><tbody>\
            <tr><td><input type="checkbox" value="absent"/></td><td> Not present</td></tr>\
            <tr><td><input type="checkbox" value="rude"/></td><td> Rude</td></tr>\
            <tr><td><input type="checkbox" value="debuging"/></td><td> Debugging help</td></tr>\
            <tr><td><input type="checkbox" value="conceptual"/></td><td> Conceptual help</td></tr>\
            <tr><td><input type="checkbox" value="design"/></td><td> Design help</td></tr>\
            <tr><td><input type="checkbox" value="grub"/></td><td> Wanted answers, not learning</td></tr>\
            <tr><td><input type="checkbox" value="check"/></td><td> Pre-grading; <q>is this OK</q></td></tr>\
            <tr><td><input type="checkbox" value="tech"/></td><td> Computer/site/systems help</td></tr>\
            <tr><td><input type="checkbox" value="read"/></td><td> Didn\'t read</td></tr>\
            <tr><td><input type="checkbox" value="other"/></td><td> Other</td></tr>\
            </tbody></table>\
            <input type="button" value="Finished helping" onclick="resolve()"/>\
            <input type="button" value="Return to queue unhelped" onclick="unhelp()"/>';
//            <input type="button" value="View your help history" onclick="history()"/>';
            document.title = 'Helping ('+data.crowd + ' waiting people)';
        } else if (kind == "ta-history") {
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
            if (content.lastElementChild.tagName.toLowerCase() == 'table')
                content.removeChild(content.lastElementChild);
            content.appendChild(tab);
            // console.log(tab);
        } else if (kind == "ta-set") {
            var tas = data.tas.sort().filter(function(el,i,a){return !i||el!=a[i-1];});
            console.log(tas);
            document.getElementById("misc").innerHTML = tas.length + " TA"+(tas.length == 1 ? '' : 's')+" online: <span class='ta'>" + tas.join("</span> <span class='ta'>") + "</span>";
        } else if (kind == "reauthenticate") {
            window.location.reload(false);
            setText("Unexpected message \""+kind+"\" (please report this to the professor if it stays on the screen)");
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

function sendForm() {
    var obj = {};
    var ins = document.getElementsByTagName('input');
    for(var i=0; i<ins.length; i+=1)
        if (ins[i].name) {
            if (!ins[i].value) {
                alert("Failed to provide "+(
                    ins[i].name == 'where' ? "your location" : 
                    ins[i].name == 'what' ? "your task" : 
                    ins[i].name));
                return;
            }
            obj[ins[i].name] = ins[i].value;
        }
    ins = document.getElementsByTagName('select');
    for(var i=0; i<ins.length; i+=1)
        if (ins[i].name) {
            if (!ins[i].value) {
                alert("Failed to provide "+(
                    ins[i].name == 'where' ? "your location" : 
                    ins[i].name == 'what' ? "your task" : 
                    ins[i].name));
                return;
            }
            obj[ins[i].name] = ins[i].value;
        }
    socket.send(JSON.stringify(obj));
}

function resolve() {
    var message = [];
    var ins = document.getElementsByTagName('input');
    for(var i=0; i<ins.length; i+=1)
        if (ins[i].checked)
            message.push(ins[i].value);
    
    socket.send('{"req":"resolve","notes":"'+message+'"}');
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
function unhelp() {
    socket.send('{"req":"unhelp"}');
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

function getBaseURL() {
    var wsurl = "wss://" + window.location.hostname+':1111' // not ':'+window.location.port
    return wsurl;
}
    //--></script>
    <style>
        #wrapper { 
            padding:1em; border-radius:1em; background:white;
        }
        body { background: #dad0dd; font-family: sans-serif; }
        pre#timer {
            border: 1px solid grey;
            color: grey;
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
    </style>
</head>
<body onLoad="connect()">
    <div id="wrapper">
        <p>TA office hours are held in Thorton Stacks, see <a href='https://cs1110.cs.virginia.edu/oh.html'>the course website</a> for office hour times.</p>
        <div id="content"></div>
        <div id="misc"></div>
        <pre id="timer">(client-server status log)</pre>
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
