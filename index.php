<!DOCTYPE html>
<html>

<head>
	<meta charset="UTF-8"> 
    <title>ATonymous</title>
    <link rel="shortcut icon" type="image/ico" href="favicon.ico"/>
    <link rel="stylesheet" type="text/css" href="style.css"> 
</head>

<body> <?php
    /* Getting data from MySQL database -- START */

    $conn = @mysqli_connect("localhost", "root", "", "atonymous") or die("Could not connect to database.");

    $password = "";

    $select = mysqli_query($conn, "SELECT * FROM settings WHERE setting = 'password';");

    while ($row = $select->fetch_assoc()) {
        $password = $row["value"];
    }

    $enabled = false;

    $select = mysqli_query($conn, "SELECT * FROM settings WHERE setting = 'enabled';");

    while ($row = $select->fetch_assoc()) {
        $enabled = intval($row["value"]);
    }

    $names = array();
    $webhooks = array();

    $select = mysqli_query($conn, "SELECT * FROM channels;");

    while ($row = $select->fetch_assoc()) {
        array_push($names, $row["name"]);
        array_push($webhooks, $row["webhook"]);
    }

    /* Getting data from MySQL database -- END */

    session_start();
    
    $signature = session_id();
    $salt = sha1(md5($signature));
    $signature = md5($signature . $salt);

    if (!isset($_GET["lastchannel"])) {
        $_GET["lastchannel"] = 1;
    }
    
    if (!isset($_GET["signature"])) {
        $_GET["signature"] = 0;
    } 
    
    $avatartooltip = "Make sure that the URL is correct, or else your messages won't send. GIFs don't work.";

    $signaturetoolip = "Embeds a signature into your message based on your unique session ID. 
                        To generate a new signature, untick the checkbox and submit this form 
                        (e.g. send a blank message), then you may tick it again."; ?>

    <form method="post">
        <input name="username" type="text" maxlength="32" placeholder="username (optional)" value="<?php if (isset($_GET["username"])) echo $_GET["username"]; ?>"><br>
        <input name="avatar" type="text" placeholder="avatar url (optional)" value="<?php if (isset($_GET["avatar"])) echo $_GET["avatar"]; ?>"><label data-tooltip="<?php echo $avatartooltip; ?>">*</label><br>
        <textarea name="message" rows="5" maxlength="2000" placeholder="message" autofocus></textarea><br>      
        <input name="signature" type="checkbox" <?php if ($_GET["signature"] == 1) echo "checked"; ?>><label data-tooltip="<?php echo $signaturetoolip; ?>">signature</label>
        <input name="tts" type="checkbox"><label>tts</label>
        <input name="embed" type="checkbox"><label>embed</label><br>
        <select name="channel"><?php
        foreach ($names as $key => $name) { ?>
            <option value="<?php echo $key; ?>" <?php if ($_GET["lastchannel"] == $key) echo "selected"; ?>>#<?php echo $name; ?></option><?php
        } ?>
        </select><?php
        if ($enabled) { ?>
            <input id="send" name="send" type="submit" value="send message"><?php
        }
        if (isset($_GET["messageerror"])) { ?>
            <br><label class="error">enter a message</label><?php
        } ?><br><br>
        <input class="short" name="password" type="password" placeholder="password">
        <input id="toggle" name="toggle" type="submit" value="<?php echo $enabled ? "disable" : "enable"; ?>"><?php
        if (isset($_GET["passworderror"])) { ?>
            <br><label class="error">incorrect password</label><?php
        } ?>
    </form> <?php

    if (isset($_POST["send"])) {
        if (strlen(trim($_POST["message"])) > 0) {  
            //Sending message (message is automatically sent as an embed if it contains "@")
            if (isset($_POST["signature"])) {
                //message embedded; signature shown
                if (strpos($_POST["message"], "@") !== false || isset($_POST["embed"])) {
                    $hookobject = json_encode([ 
                        "username" => $_POST["username"], 
                        "avatar_url" => $_POST["avatar"], 
                        "tts" => isset($_POST["tts"]), 
                        "embeds" => [
                            [
                                "description" => $_POST["message"], 
                                "footer" => ["text" => "Signature: " . $signature]
                            ]
                        ]
                    ]);
                } else { //message not embedded; signature shown
                    $hookobject = json_encode([
                        "content" => $_POST["message"], 
                        "username" => $_POST["username"], 
                        "avatar_url" => $_POST["avatar"], 
                        "tts" => isset($_POST["tts"]), 
                        "embeds" => [
                            [
                                "footer" => ["text" => "Signature: " . $signature]
                            ]
                        ]
                    ]);
                }
            } else {
                //message embedded; signature not shown
                if (strpos($_POST["message"], "@") !== false || isset($_POST["embed"])) {
                    $hookobject = json_encode([
                        "username" => $_POST["username"], 
                        "avatar_url" => $_POST["avatar"], 
                        "tts" => isset($_POST["tts"]), 
                        "embeds" => [
                            [
                                "description" => $_POST["message"]
                            ]
                        ]
                    ]);
                } else { //no embed; signature not shown
                    $hookobject = json_encode([
                        "content" => $_POST["message"], 
                        "username" => $_POST["username"], 
                        "avatar_url" => $_POST["avatar"], 
                        "tts" => isset($_POST["tts"])
                    ]);
                }
            }

            $curl = curl_init($webhooks[intval($_POST["channel"])]);
    
            curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-type: application/json"));
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $hookobject);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($curl, CURLOPT_HEADER, 0);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    
            curl_exec($curl);
    
            //Determining GET parameters   
            if (!$_POST["username"] == "") {
                $username = "&username=" . $_POST["username"];
            }
    
            if (!$_POST["avatar"] == "") {
                $avatar = "&avatar=" . $_POST["avatar"];
            }
    
            if (isset($_POST["signature"])) {
                $signature = "&signature=1";
            } else {
                $signature = "";
                session_regenerate_id();
            }

            //Post-Redirect-Get
            header("Location: " . $_SERVER["PHP_SELF"] . "?lastchannel=" . $_POST["channel"] . $signature . $username . $avatar);
            exit();
        } else {
            //Post-Redirect-Get
            header("Location: " . $_SERVER["PHP_SELF"] . "?messageerror");
            exit();
        }
    }
    
    if (isset($_POST["toggle"])) {
        if (password_verify($_POST["password"], $password)) {
            $enabled = $enabled ? "0" : "1";

            mysqli_query($conn, "UPDATE settings SET value = $enabled WHERE setting = 'enabled';");

            header("Location: " . $_SERVER["PHP_SELF"]);
        } else {
            //Post-Redirect-Get
            header("Location: " . $_SERVER["PHP_SELF"] . "?passworderror");
            exit();
        }
    } ?>

    <script>
        document.onkeydown = function(evt) {     
            if (evt.keyCode == 13 && !evt.shiftKey) {
                document.getElementById("send").click();
            }
        }
    </script>
</body>

</html>