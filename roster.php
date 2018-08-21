<!DOCTYPE html>
<html>
<head>
    <title>Office Hours Roster Uploader</title>
<?php
$user = $_SERVER['PHP_AUTH_USER'];
if ($user == "lat7h" && $_GET['user']) $user=$_GET['user'];
// if (strpos($_GET['user'], 'student_') === 0) $user = $_GET['user']; // beta only!
?>
</head>
<body>
    <pre><?php
if ($_POST['course'] && $_FILES['file'] && $_FILES['file']['tmp_name']) {
    $url = "https://archimedes.cs.virginia.edu:1111/roster/$_POST[course]";
    $data = json_encode(array(
        "user" => $user,
        "token" => file_get_contents("/opt/ohq/logs/sessions/$user"),
        "course" => $_POST['course'],
        "filename" => $_FILES['file']['tmp_name'],
    ));
    $options = array('http' => array(
        'header' => 'Content-type: application/json\r\n',
        'method' => 'POST',
        'content' => $data,
    ));
    $context = stream_context_create($options);
    $results = file_get_contents($url, false, $context);
    var_dump($result);
} else {
    $token = bin2hex(openssl_random_pseudo_bytes(4)) . " " . date(DATE_ISO8601);
    file_put_contents("/opt/ohq/logs/sessions/$user", "$token");
}
    ?></pre>
    <form enctype="multipart/form-data" method="POST" action="roster.php">
    <p>
        What course? <input type="text" name="course" id="course"></input><br/>
        This should contain only alpanums, as e.g. <tt>cs1110</tt> or <tt>coa1</tt>.
        You should have been told what to use when you were given an account on this machine.
    </p>
    <p>
        <input type="file" name="file" id="file"></input></br>
        Please upload a roster <tt>.xlsx</tt> exactly as exported from the Collab roster tool.
        At the present time, manually edited rosters are not supported
        even if visually identical to a Collab roster.
    </p>
    <p><input type="submit"></input></p>
    </form>
</body>
</html>
