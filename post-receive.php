<?php
// require_once("config.php");
// $content = file_get_contents("php://input");
// $json    = json_decode($content, true);
// $file    = fopen(LOGFILE, "a");
// $time    = time();
// $token   = false;
// // retrieve the token
// if (!$token && isset($_SERVER["HTTP_X_HUB_SIGNATURE"])) {
//     list($algo, $token) = explode("=", $_SERVER["HTTP_X_HUB_SIGNATURE"], 2) + array("", "");
// } elseif (isset($_SERVER["HTTP_X_GITLAB_TOKEN"])) {
//     $token = $_SERVER["HTTP_X_GITLAB_TOKEN"];
// } elseif (isset($_GET["token"])) {
//     $token = $_GET["token"];
// }
// // log the time
// date_default_timezone_set("UTC");
// fputs($file, date("d-m-Y (H:i:s)", $time) . "\n");
// // function to forbid access
// function forbid($file, $reason) {
//     // explain why
//     if ($reason) fputs($file, "=== ERROR: " . $reason . " ===\n");
//     fputs($file, "*** ACCESS DENIED ***" . "\n\n\n");
//     fclose($file);
//     // forbid
//     header("HTTP/1.0 403 Forbidden");
//     exit;
// }
// // function to return OK
// function ok() {
//     ob_start();
//     header("HTTP/1.1 200 OK");
//     header("Connection: close");
//     header("Content-Length: " . ob_get_length());
//     ob_end_flush();
//     ob_flush();
//     flush();
// }
// // Check for a GitHub signature
// if (!empty(TOKEN) && isset($_SERVER["HTTP_X_HUB_SIGNATURE"]) && $token !== hash_hmac($algo, $content, TOKEN)) {
//     forbid($file, "X-Hub-Signature does not match TOKEN");
// // Check for a GitLab token
// } elseif (!empty(TOKEN) && isset($_SERVER["HTTP_X_GITLAB_TOKEN"]) && $token !== TOKEN) {
//     forbid($file, "X-GitLab-Token does not match TOKEN");
// // Check for a $_GET token
// } elseif (!empty(TOKEN) && isset($_GET["token"]) && $token !== TOKEN) {
//     forbid($file, "\$_GET[\"token\"] does not match TOKEN");
// // if none of the above match, but a token exists, exit
// } elseif (!empty(TOKEN) && !isset($_SERVER["HTTP_X_HUB_SIGNATURE"]) && !isset($_SERVER["HTTP_X_GITLAB_TOKEN"]) && !isset($_GET["token"])) {
//     forbid($file, "No token detected");
// } else {
//     // check if pushed branch matches branch specified in config
//     if ($json["ref"] === BRANCH) {
//         fputs($file, $content . PHP_EOL);
//         // ensure directory is a repository
//         if (file_exists(DIR . ".git") && is_dir(DIR)) {
//             try {
//                 // pull
//                 chdir(DIR);
//                 shell_exec(GIT . " pull");
//                 // return OK to prevent timeouts on AFTER_PULL
//                 ok();
//                 // execute AFTER_PULL if specified
//                 if (!empty(AFTER_PULL)) {
//                     try {
//                         shell_exec(AFTER_PULL);
//                     } catch (Exception $e) {
//                         fputs($file, $e . "\n");
//                     }
//                 }
//                 fputs($file, "*** AUTO PULL SUCCESFUL ***" . "\n");
//             } catch (Exception $e) {
//                 fputs($file, $e . "\n");
//             }
//         } else {
//             fputs($file, "=== ERROR: DIR is not a repository ===" . "\n");
//         }
//     } else{
//         fputs($file, "=== ERROR: Pushed branch does not match BRANCH ===\n");
//     }
// }
// fputs($file, "\n\n" . PHP_EOL);
// fclose($file);



/*
 * Endpoint for Github Webhook URLs
 *
 * see: https://help.github.com/articles/post-receive-hooks
 *
 */
// script errors will be send to this email:
$error_mail = "admin@example.com";
function run() {
    global $rawInput;
    // read config.json
    $config_filename = 'config.json';
    if (!file_exists($config_filename)) {
        throw new Exception("Can't find ".$config_filename);
    }
    $config = json_decode(file_get_contents($config_filename), true);
    $postBody = $_POST['payload'];
    $payload = json_decode($postBody);
    if (isset($config['email'])) {
        $headers = 'From: '.$config['email']['from']."\r\n";
        $headers .= 'CC: ' . $payload->pusher->email . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
    }
    // check if the request comes from github server
    $github_ips = array('207.97.227.253', '50.57.128.197', '108.171.174.178', '50.57.231.61');
    if (in_array($_SERVER['REMOTE_ADDR'], $github_ips)) {
        foreach ($config['endpoints'] as $endpoint) {
            // check if the push came from the right repository and branch
            if ($payload->repository->url == 'https://github.com/' . $endpoint['repo']
                && $payload->ref == 'refs/heads/' . $endpoint['branch']) {
                // execute update script, and record its output
                ob_start();
                passthru($endpoint['run']);
                $output = ob_end_contents();
                // prepare and send the notification email
                if (isset($config['email'])) {
                    // send mail to someone, and the github user who pushed the commit
                    $body = '<p>The Github user <a href="https://github.com/'
                    . $payload->pusher->name .'">@' . $payload->pusher->name . '</a>'
                    . ' has pushed to ' . $payload->repository->url
                    . ' and consequently, ' . $endpoint['action']
                    . '.</p>';
                    $body .= '<p>Here\'s a brief list of what has been changed:</p>';
                    $body .= '<ul>';
                    foreach ($payload->commits as $commit) {
                        $body .= '<li>'.$commit->message.'<br />';
                        $body .= '<small style="color:#999">added: <b>'.count($commit->added)
                            .'</b> &nbsp; modified: <b>'.count($commit->modified)
                            .'</b> &nbsp; removed: <b>'.count($commit->removed)
                            .'</b> &nbsp; <a href="' . $commit->url
                            . '">read more</a></small></li>';
                    }
                    $body .= '</ul>';
                    $body .= '<p>What follows is the output of the script:</p><pre>';
                    $body .= $output. '</pre>';
                    $body .= '<p>Cheers, <br/>Github Webhook Endpoint</p>';
                    mail($config['email']['to'], $endpoint['action'], $body, $headers);
                }
                return true;
            }
        }
    } else {
        throw new Exception("This does not appear to be a valid requests from Github.\n");
    }
}
try {
    if (!isset($_POST['payload'])) {
        echo "Works fine.";
    } else {
        run();
    }
} catch ( Exception $e ) {
    $msg = $e->getMessage();
    mail($error_mail, $msg, ''.$e);
}