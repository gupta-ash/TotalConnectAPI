<?php
/** Total Connect Control */

//=== CHANGE Between Dev and Production Servers ====//
define("TC2FILE", "/home/TC2/TC2Login.txt");
define("LOGFILE", "/home/TC2/TC2.log");
define("CURL_SSL_VERIFY", TRUE);   // On dev server only!
//===================================================//

// ==== CHANGE THESE IF LOGIN/PASS changes ==========//
define("USERNAME", "MyTC2Username"); // This is your TotalConnect Username. Create a new user for Home Automation
define("PASSWD", "MyTC2Password");   // This is your TotalConnect password

// ==== CHANGE THESE to Secure this script with your own Login/Passwd ==========//
define("MYUSERID", "MyProxyLogin");            //Since this script provides a blanket access to your TotalConnect account. Configure the script to use a username/passwd authentication. If the browser/automation request doesn't include this username/password, the script will disconnect.
define("MYPASSWD", "MyProxyPasswd");
//===================================================//

// Stays Static. No Need to change unless TC changes on their end
define("APPID", "14588");
define("APPVER", "3.16.5");
define("LOCALE", "en-US");
define("TC2URL", "https://rs.alarmnet.com/TC21API/TC2.asmx/");
//===================================================//
// Used as Index to Store Info in TC2File


// Used as Index to Store Info in TC2File
define("SESSIONID", "SessionID");
define("LOCATIONID", "LocationID");
define("DEVICEID", "DeviceID");

// Arguments to be passed, also Index for armArray
define("ARMAWAY", "ARMAWAY");
define("AWAYBYPASS", "AWAYBYPASS");
define("ARMSTAY", "ARMSTAY");
define("STAYBYPASS", "ARMSTAYBYPASS");
define("DISARM", "DISARM");
define("STATUS", "STATUS");

// Constants used in the Multidimensional Array: armArray
define("URLCOMMAND",0);
define("ARMCODE",1);
define("ALARMSTATE",2);

$armArray = array
(
    "Command" => array("URLCommand","ArmCode","AlarmStatus"),
    ARMAWAY => array("ArmSecuritySystem","0","10201"),
    ARMSTAY => array("ArmSecuritySystem","1","10203"),
    DISARM => array("DisarmSecuritySystem","0","10200"),
);

define("USERCODE", "-1");        // User Code to Use to Arm/Disarm system

function authenticateUser() {
    if (!isset($_SERVER['PHP_AUTH_USER'])) {
        header('WWW-Authenticate: Basic realm="My Realm"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Unauthorized';
        return false;
    }

    if ( ($_SERVER['PHP_AUTH_USER'] == MYUSERID) && ($_SERVER['PHP_AUTH_PW'] == MYPASSWD))
        return true;
    else {
        header('WWW-Authenticate: Basic realm="My Realm"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Unauthorized';
        return false;
    }
}

function postToTC2($postPage, $postfields) {
    $url = TC2URL . $postPage;
    //print "Calling URL: " . $url . "\n";
    error_log("Calling URL: " . $url . "\n",3, LOGFILE);

    $ch = curl_init();
    //curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, CURL_SSL_VERIFY); // On dev server only!
    $result = curl_exec($ch);

    return $result;
}

function validateSession($sessionID) {
    $postPage = "GetSessionDetails";
    $data = "SessionID=" . $sessionID . "&ApplicationID=" . APPID . "&ApplicationVersion=" . APPVER;
    $result = postToTC2($postPage, $data);

    $xml = simplexml_load_string($result);

    $resultcode = $xml->ResultCode[0];
    $resultdata = $xml->ResultData[0];

    if (($resultcode == "0") && ($resultdata == "Success")) {
        //print "ValidateSession: Saved SessionID : $sessionID still active. Reusing" . PHP_EOL;
        return true;
    }
    else
        if ($resultcode == "-102") {
            //print "ValidateSession: Saved SessionID failed. Response: $resultdata" . PHP_EOL;
            return false;
        }
    //print "ValidateSession: unknown error. ResultCode: $resultcode ResultData: $resultdata" . PHP_EOL;
    error_log("ValidateSession: unknown error. ResultCode: $resultcode ResultData: $resultdata" . PHP_EOL, 3, LOGFILE);
    return false;
}

function saveLoginInfo($loginArray) {
    file_put_contents (TC2FILE, json_encode($loginArray));
}

function loadLoginInfo() {
    if (!file_exists(TC2FILE)) {
        error_log("loadLoginInfo: Cache file doesnt exist. Need to login(): " . TC2FILE . PHP_EOL, 3, LOGFILE);
        return false;
    }

    if (!$loadString = file_get_contents(TC2FILE)){
        //print "loadLoginInfo: Could not open file: " . TC2FILE . PHP_EOL;
        error_log("loadLoginInfo: Could not open file: " . TC2FILE . PHP_EOL, 3, LOGFILE);
        return false;
    }

    $jsonArray = json_decode($loadString,true);
    $loginArray = array(
        SESSIONID => $jsonArray[SESSIONID],
        LOCATIONID => $jsonArray[LOCATIONID],
        DEVICEID => $jsonArray[DEVICEID],
    );
//    echo "Size: " . count($loginArray) , PHP_EOL;;
    return $loginArray;
}

function login() {
    if ($loginArray = loadLoginInfo()) {    // Means Login File Exists. May resuse
        $sessionID = $loginArray[SESSIONID];
        if (validateSession($sessionID)) {
            //print "login: Saved SessionID : $sessionID still active. Reusing" . PHP_EOL;
            error_log("login: Saved SessionID : $sessionID still active. Reusing" . PHP_EOL, 3, LOGFILE);
            return $loginArray;
        }
        else
            //print "login: Saved SessionID : $sessionID expired. Need to login again." . PHP_EOL;
            error_log("login: Saved SessionID : $sessionID expired. Need to login again." . PHP_EOL, 3, LOGFILE);
    }
    //else
        //print "Loading File failed. Need to login 1st. \n";
        //error_log("Loading File failed. Need to login 1st." . PHP_EOL, 3, LOGFILE);

    $postPage = "LoginAndGetSessionDetails";
    $data = "userName=" . USERNAME . "&password=" . PASSWD . "&ApplicationID=" . APPID . "&ApplicationVersion=" . APPVER . "&LocaleCode=" . LOCALE;
    $result = postToTC2($postPage, $data);
    error_log("Login RESULT: $result " . PHP_EOL, 3, LOGFILE);
    // echo("RESULT: $result " . PHP_EOL);

    // ASHTODO: Need to figure out what to do if I have error in result
    $xml = simplexml_load_string($result);
    $json = json_encode($xml->Locations->LocationInfoBasic->DeviceList);
    $array = json_decode($json,TRUE);

    $myDeviceID = "";
    foreach ($array["DeviceInfoBasic"] as $device) {
        if ($device["DeviceClassID"] == "1") {
            //echo $device["DeviceClassID"] . $device["DeviceName"] . $device["DeviceID"] . PHP_EOL;
            $myDeviceID = $device["DeviceID"];
        }
    }

    $loginArray = array(
        SESSIONID => $xml->SessionID[0]->__toString(),
        LOCATIONID => $xml->Locations->LocationInfoBasic->LocationID[0]->__toString(),
        DEVICEID => $myDeviceID,
    );
    saveLoginInfo($loginArray);
    return $loginArray;
}

function armDisarm($command, $armArray) {
    $loginArray = login();

    $postPage = $armArray[URLCOMMAND];
    $data = "SessionID=" . $loginArray[SESSIONID] . "&LocationID=" . $loginArray[LOCATIONID] . "&DeviceID=" . $loginArray[DEVICEID] . "&UserCode=" . USERCODE;

    if (($command == ARMAWAY) || ($command == ARMSTAY)) {
        $data = $data . "&ArmType=" . $armArray[ARMCODE];
    }

    $result = postToTC2($postPage, $data);

    $xml = simplexml_load_string($result);
    $resultCode = $xml->ResultCode[0]->__toString();
    $resultData = $xml->ResultData[0]->__toString();
    //echo "ARMDISARM: ResultCode: $resultCode Data: $resultData \n";
    error_log("ARMDISARM: ResultCode: $resultCode Data: $resultData" . PHP_EOL, 3, LOGFILE);

    //Poll for Status Completion of the command
    while (($resultCode == "4500") || ($resultCode == "4501")){   // 4500=Command Session Initiated.  4501=Command Scheduled/Working  0=Command Completed
        sleep(2);
        $postPage = "CheckSecurityPanelLastCommandState";
        $data = "SessionID=" . $loginArray[SESSIONID] . "&LocationID=" . $loginArray[LOCATIONID] . "&DeviceID=" . $loginArray[DEVICEID] . "&CommandCode=-1";
        $result = postToTC2($postPage, $data);
        $xml = simplexml_load_string($result);
        $resultCode = $xml->ResultCode[0]->__toString();
        $resultData = $xml->ResultData[0]->__toString();
        //echo "ARMDISARM: ResultCode: $resultCode Data: $resultData \n";
        error_log("ARMDISARM: ResultCode: $resultCode Data: $resultData" . PHP_EOL, 3, LOGFILE);
    }

    if ($resultCode == "0") {
        //Check Metadata that current state is ArmAway/Stay/Disarm
        $postPage = "GetPanelMetaDataAndFullStatus";
        $data = "SessionID=" . $loginArray[SESSIONID] . "&LocationID=" . $loginArray[LOCATIONID] . "&LastSequenceNumber=0&LastUpdatedTimestampTicks=0&PartitionID=1";
        $result = postToTC2($postPage, $data);
        $xml = simplexml_load_string($result);
        $alarmState = $xml->PanelMetadataAndStatus->Partitions->PartitionInfo->ArmingState[0]->__toString();
        //echo "AlarmState: $alarmState \n";
        error_log("AlarmState: $alarmState" . PHP_EOL, 3, LOGFILE);
        if ($alarmState == $armArray[ALARMSTATE]) {
            // echo "Alarm Successful: $command Current State: $alarmState \n";
            error_log("Alarm Successful: $command Current State: $alarmState" . PHP_EOL, 3, LOGFILE);
            return true;
        }
        else
            //echo "ERROR !!! ERROR !!! Alarm ARM/DISARM Failed: $command Current State: $alarmState \n";
            error_log("ERROR !!! ERROR !!! Alarm ARM/DISARM Failed: $command Current State: $alarmState" . PHP_EOL, 3, LOGFILE);
    }
    else
        //echo "ERROR !!! ERROR !!! Alarm ARM/DISARM Failed: $resultCode  Data: $resultData \n";
        error_log("ERROR !!! ERROR !!! Alarm ARM/DISARM Failed: $resultCode  Data: $resultData" . PHP_EOL, 3, LOGFILE);

    return false;
}

function systemStatus($armArray, $loginArray) {
    $systemStatusArray = array(
        '10200' => DISARM,
        '10201' => ARMAWAY,
        '10202' => AWAYBYPASS, // This is Actually ARMAWAY_BYPASS
        '10203' => ARMSTAY,
        '10204' => STAYBYPASS,
        '10205' => ARMAWAY,
    );

    if (is_null($loginArray))
        $loginArray = login();

    $postPage = "GetPanelMetaDataAndFullStatus";
    $data = "SessionID=" . $loginArray[SESSIONID] . "&LocationID=" . $loginArray[LOCATIONID] . "&LastSequenceNumber=0&LastUpdatedTimestampTicks=0&PartitionID=1";
    $result = postToTC2($postPage, $data);
    $xml = simplexml_load_string($result);
    $alarmState = $xml->PanelMetadataAndStatus->Partitions->PartitionInfo->ArmingState[0]->__toString();
    //echo "AlarmState: " . $alarmState . " = " . $systemStatusArray[$alarmState] . PHP_EOL;

    return $systemStatusArray[$alarmState];
    /*
        foreach ($armArray as $command => $state)
            if ($state[ALARMSTATE] == $alarmState) {
                // print "My Status is: $alarmState  = " . $command . PHP_EOL;
                error_log("My Status is: $alarmState  = " . $command . PHP_EOL, 3, LOGFILE);
                return $command;
            }
        echo "STATUS ERROR: Unknown Status of the Panel. Status: $alarmState \n";
        error_log("STATUS ERROR: Unknown Status of the Panel. Status: $alarmState" . PHP_EOL, 3, LOGFILE);
        return $alarmState;
    */
}

function zoneStatus($loginArray) {
    // Zone status Information is below
    // 0 – Normal
    // 1 – Bypassed
    // 2 – Faulted
    // 8 – Trouble
    // 16 – Tampered
    // 32 – Supervision Failed

    $zoneStatusArray = array(
        '0' => 'CLOSED',
        '1' => 'BYPASS',
        '2' => 'OPEN',
        '8' => 'TROUBLE',
        '16' => 'TAMPER',
        '32' => 'SUPER_FAIL',
    );


    $postPage = "GetZonesListInStateEx";
    $data = "SessionID=" . $loginArray[SESSIONID] . "&LocationID=" . $loginArray[LOCATIONID] . "&PartitionID=1&ListIdentifierID=0";
    $result = postToTC2($postPage, $data);
    $xml = simplexml_load_string($result);
    var_dump($xml);
    // error_log($xml, 0);
   echo "";

    $zoneStatus = "";
    foreach($xml->ZoneStatus->Zones->ZoneStatusInfoEx as $zone) {
        $zoneStatus .= "Zone" . $zone['ZoneID'] . "=" . $zoneStatusArray[$zone['ZoneStatus']->__toString()] . ";";
    }
    return $zoneStatus;
}

function status($armArray) {
    // In OpenHAB, Use following Regex to Parse:
    // REGEX(.*SysStatus=([a-zA-Z]+))
    // REGEX(.*Zone2=([a-zA-Z_]+))
    // REGEX(.*Zone46=([a-zA-Z_]+))

    $loginArray = login();

    $status = "SysStatus=" . systemStatus($armArray, $loginArray) . ";";
    $status .= zoneStatus($loginArray);
    return $status;
}

// ==========================================================================================
// Main Class starts here
// ==========================================================================================

if (!authenticateUser())
    exit(false);

if (count($_GET) < 1) {
    print "Usage: http://localhost/TC2Proxy?action=[ARMSTAY, ARMAWAY, DISARM, STATUS, SYSSTATUS]\n\n";
    exit(false);
}

if (!isset($_GET['action'])) {
    print "Usage: http://localhost/TC2Proxy?action=[ARMSTAY, ARMAWAY, DISARM, STATUS, SYSSTATUS]\n\n";
    exit(false);
}

$command = $_GET['action'];
switch ($command) {
    case "DISARM":
    case "ARMSTAY":
    case "ARMAWAY":
        $success = armDisarm($command, $armArray[$command]);
        print $success . PHP_EOL;
        exit(0);
        break;
    case "STATUS":
        $mystatus = status($armArray);
        print $mystatus . PHP_EOL;
        exit(0);
        break;
    case "SYSSTATUS":
        $mystatus = systemStatus($armArray, null);
        print $mystatus . PHP_EOL;
        exit(0);
        break;
    default:
        print "Usage: http://localhost/TC2Proxy?action=[ARMSTAY, ARMAWAY, DISARM, STATUS]\n\n";
        exit(false);
}

?>
