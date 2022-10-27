<?php

header('Access-Control-Allow-Origin: *');

// additional setting to make communication with API easier
if( isset($_REQUEST['sid']) ) session_id($_REQUEST['sid']);

session_start();

// This API needs so much more work that
// for now shouldn't be even considered beta
// PS. It's my second php script in my life
// If you see any bugs or things I could do better
// please commit your change or tell me about it
// on discord or any other media (can be found on https://www.yukiteru.xyz)

//  Exit codes:
//      0 - Success
//      1 - Something went wrong (server-side) ex. can't connect to database
//      2 - Can't find user with such name/email
//      3 - Wrong password
//      4 - Wrong email
//      5 - User with this name already exists
//      6 - User with this email already exits
//      7 - Session expired
//      8 - Bad request

include('../credentials.php');
include('./utils.php');

$settings = file_get_contents('../settings.json');
$settings = json_decode($settings, true);

$MySQLDatabase = new mysqli('localhost', $MySQLuser, $MySQLpassword, $MySQLdbname);

if( $MySQLDatabase->connect_errno ) returnResult('Something went wrong, try again later.', 1);

// Python backend caller
function researcher(string $query){
    global $settings;
    
    $ip   = $settings['researcherIp'];
    $port = $settings['researcherPort'];
    
    $response = post("http://${ip}:${port}/${query}");
    
    return json_decode($response, true);
}

function stmt_bind_assoc (&$stmt, &$out) {
    $data = mysqli_stmt_result_metadata($stmt);
    $fields = array();
    $out = array();

    $fields[0] = $stmt;
    $count = 1;

    while($field = mysqli_fetch_field($data)) {
        $fields[$count] = &$out[$field->name];
        $count++;
    }   
    call_user_func_array(mysqli_stmt_bind_result, $fields);
}

//----------------------------------\
//                                  |
//     Database related stuff       |
//                                  |
//----------------------------------/

//----------------------\
//  Email Verification  |
//----------------------/

// checks if user with specified email is verified and returns true or false
function isEmailVerified($email): int {
    global $MySQLDatabase;

    $sql      = "SELECT `verificationCode` FROM `Users` WHERE `email` = '$email';";
    $response = $MySQLDatabase->query($sql);

    if($code = $response->fetch_row())
        return $code == 'verified';

    return false;
}

// 0 - ok 1- already done
function sendVerificationEmail($email): int {
    global $MySQLDatabase;

    try   { $code = bin2hex(random_bytes(4)); }
    catch ( Exception $e ) { return 1; }

    $sql = "UPDATE `Users` SET `verificationCode` = '$code' WHERE `email` = '$email';";

    if(!$MySQLDatabase->query($sql)) return 1;

    mail($email, 'Kokonoe - verification email.', "Your code: $code");

    return 0;
}

// 0 - correct 1 - not correct
function confirmUserEmail(string $email, $code): int {
    global $MySQLDatabase;

    $sql = "SELECT `verificationCode` FROM `users` WHERE `email` = '$email';";

    $response = $MySQLDatabase->query($sql);

    if($response->fetch_row()[0] != $code)
        return 1;

    $sql = "UPDATE `users` SET `verificationCode` = 'verified' WHERE `email` = '$email';";

    $response = $MySQLDatabase->query($sql);

    if(!$response) return 1;

    return 0;
}

//--------------------\
// account management |
//--------------------/

function signIn($username, $password): int {
    global $MySQLDatabase;

    $username = $MySQLDatabase->real_escape_string($username);
    $password = $MySQLDatabase->real_escape_string($password);

    $sql      = "SELECT `id`, (`password` = '$password') AS `authenticated` FROM `users` WHERE `username` = '$username' OR `email` = '$username';";

    $response = $MySQLDatabase->query($sql);
    $row      = $response->fetch_assoc();

    if ( ! $response->num_rows   ) return 1;
    if ( ! $row['authenticated'] ) return 2;

    $_SESSION['migurdia']['userID'] = (int) $row['id'];

    return 0;
}

function signup($username, $email, $password): int {
    global $MySQLDatabase;

    $email    = strtolower($email);

    $username = $MySQLDatabase->real_escape_string($username);
    $email    = $MySQLDatabase->real_escape_string($email   );
    $password = $MySQLDatabase->real_escape_string($password);

    $sql = "SELECT `username`, `email` FROM `users` WHERE `users`.`username` = '$username' OR `users`.`email` = '$email'";

    $response = $MySQLDatabase->query($sql);

    if($row = $response->fetch_assoc()) return ( strtolower($row['username']) == strtolower($username) ? 1 : 2 );

    $sql = "INSERT INTO `users` (`username`, `email`, `password`, `verificationCode`) VALUES ('$username', '$email', '$password', '00000000');";

    if( ! $MySQLDatabase->query($sql) )
        return 3;

    return 0;
}

function isSignedIn(): int{
    if( isset($_SESSION['kokonoe']['userID']) )
        if(   $_SESSION['kokonoe']['userID']  ) return true;

    return false;
}

//-------------------\
// Tag related stuff |
//-------------------/

// returns array(array(tagID, tag), array(tagID, tag)...)
function getTagProposals(array &$result, string $hint, int $limit=20): int {
    global $MySQLDatabase;

    $hint = $MySQLDatabase->real_escape_string($hint);

    $sql = "SELECT * FROM `Tags` WHERE `tag` LIKE \"%$hint%\" LIMIT $limit;";

    $response  = $MySQLDatabase->query($sql);

    while($row = $response->fetch_row())
        array_push($result, $row);

    $response->free_result();

    return 0;
}

function post(string $url, array $data=[]): ?string {
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);

    if ( !empty($data) ) curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

    $resp = curl_exec($ch);

    curl_close($ch);

    return $resp;
}

// write that fnc
function addAnimeTitle($anime){
    global $MySQLDatabase;

    $sql = $MySQLDatabase->prepare("SELECT * FROM `anime` WHERE `title` = ? AND `type` = ?;");
    $sql->bind_param('ss', $anime['title'], $anime['type']);
    
    $row = array();
    $sql->bind_result(
        $row['id'],
        $row['title'],
        $row['description'],
        $row['releaseDate'],
        $row['coverArtUrl'],
        $row['thumbnailUrl'],
        $row['episodesCount'],
        $row['type'],
        $row['shindenId'],
        $row['malRating']
    );

    $sql->execute();
    $sql->store_result();

    if($sql->num_rows > 0){
        $sql->fetch();
        return $row;
    }

    $sql = $MySQLDatabase->prepare("INSERT INTO `anime`(`title`, `description`, `coverArtUrl`, `thumbnailUrl`, `episodesCount`, `type`, `malRating`) VALUES (?,?,?,?,?,?,?);");
    $sql->bind_param('ssssiss', $anime['title'], $anime['description'], $anime['coverArtUrl'], $anime['thumbnailUrl'], $anime['episodesCount'], $anime['type'], $anime['malRating']);
    $sql->execute();

    $anime['id'] = $sql->insert_id;
    
    return $anime;
}

function searchAnimeMAL($title){
    $title = urlencode($title);

    $resp = researcher("research?title=${title}");

    if($resp == null) return null;

    $result = array();
    foreach($resp as $r) $result[] = addAnimeTitle($r);

    return $result;
}

// returns array(array(tagID, tag), array(tagID, tag)...)
function getNameProposals(array &$result, string $hint, int $limit=20): int {
    global $MySQLDatabase;

    $hint = $MySQLDatabase->real_escape_string($hint);

    $sql = "SELECT * FROM `anime` WHERE `anime`.`title` LIKE \"%$hint%\" LIMIT $limit;";

    $response  = $MySQLDatabase->query($sql);
    if(empty($result)){
        while($row = $response->fetch_assoc())
            array_push($result, $row);
    }else{
        while($row = $response->fetch_assoc()){
            $exists = false;

            foreach($result as $r) { if($r['title'] == $row['title']) {$exists = true;} }
            if (!$exists) { array_push($result, $row); }
        }
    }
    $response->free_result();

    return 0;
}

// returns internal error code
function addTags(int $postID, array $tags): int {
    global $MySQLDatabase;

    $sql = 'INSERT INTO `PostsTags` (`PostID`, `TagID`) VALUES ';
    foreach($tags as $tag) $sql .= "($postID, $tag)";

    $result = $MySQLDatabase->query($sql);

    if( !result ) return 1;

    return 0;
}

// returns tag ID
function checkTag(string $tag): int {
    global $MySQLDatabase;

    $sql = "SELECT * from `Tags` WHERE `Tag` = '$tag';";
    $result = $MySQLDatabase->query($sql);

    if($result->num_rows) return $result->fetch_assoc()['ID'];

    $sql = "INSERT INTO `Tags` (`Tag`) VALUES ('$tag');";

    $MySQLDatabase->query($sql);

    return $MySQLDatabase->insert_id;
}

//-----------------\
// File management |
//-----------------/

function optimizeURL(array &$result, string $url): int {
    global $MySQLDatabase;

    $lcUrl = strtolower($url);
    
    $sql = $MySQLDatabase->prepare("SELECT `ID`, `URL` FROM `Hostings` WHERE REGEXP_LIKE(?, `URL`);");
    $sql->bind_param('s', $lcUrl);
    $sql->execute();
    $sql->store_result();
    $sql->bind_result($hosting['ID'], $hosting['URL']);

    if($sql->num_rows == 0){
        $result['hosting'  ] = array('ID' => NULL, 'URL' => NULL);
        $result['remaining'] = $url;
        return 0;
    }

    $hostings = [];
    while($sql->fetch())
        array_push($hostings, $hosting);

    usort($hostings, function($a, $b) { return strlen($b['URL']) - strlen($a['URL']); });

    $result['hosting'  ] = $hostings[0];
    $result['remaining'] = str_replace( $hostings[0]['URL'], '', $url );

    return 0;
}

function verifyFileURL(string $url, int $tries=3): int {
    $params = array('http' => array(
        'method' => 'GET',
        'header' => 'referer: https://www.pixiv.net/'
    ));

    $ctx    = stream_context_create($params);
    $stream = @fopen($url, 'rb', false, $ctx);

    if( $stream == false ) {
        if($tries > 1) return verifyFileURL($url, $tries-1);
        return 1;
    }

    $data = fread($stream, 512);

    fclose($stream);

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);

    if( !$fileInfo->buffer($data) ) return 2;

    return 0;
}

function addPost(?int &$out, string $title, string $description, string $fileUrl, string $thumbnailUrl, array $tags, ?string $sourceUrl): int {
    global $MySQLDatabase;

    if( !isSignedIn   (             ) ) return 1;
    if(  verifyFileURL(     $fileUrl) ) return 2;
    if(  $e = verifyFileURL($thumbnailUrl) ) return $e;

    $optimizedFileUrl      = array();
    $optimizedThumbnailUrl = array();

    if( optimizeURL($optimizedFileUrl     ,      $fileUrl) ) return 4;
    if( optimizeURL($optimizedThumbnailUrl, $thumbnailUrl) ) return 5;

    $fileHosting = $optimizedFileUrl['hosting'  ]['ID'];
    $filePath    = $optimizedFileUrl['remaining'];

    $thumbnailHosting = $optimizedThumbnailUrl['hosting'  ]['ID'];
    $thumbnailPath    = $optimizedThumbnailUrl['remaining'];

    $sourceHosting = NULL;
    $sourcePath    = NULL;

    if($sourceUrl != NULL){
        $optimizedSourceUrl = array();

        optimizeURL($optimizedSourceUrl, $thumbnailUrl);
        
        $sourceHosting = $optimizedSourceUrl['hosting'  ]['ID'];
        $sourcePath    = $optimizedSourceUrl['remaining'];
    }

    $postedBy = $_SESSION['migurdia']['userID'];

    $sql = 'INSERT INTO `Posts` '
         . '(`Title`, `Description`, `PostedBy`, `FileHosting`, `FilePath`, `ThumbnailHosting`, `ThumbnailPath`, `SourceHosting`, `SourcePath`) '
         . 'VALUES (?,?,?,?,?,?,?,?,?)';

    $sql = $MySQLDatabase->prepare($sql);
    $sql->bind_param('ssiisisis',
        $title,
        $description,
        $postedBy,
        $fileHosting,
        $filePath,
        $thumbnailHosting,
        $thumbnailPath,
        $sourceHosting,
        $sourcePath
    );

    if( !$sql->execute() ) {
        var_dump(mysqli_error($MySQLDatabase));
        return 6;
    }

    $out = $sql->insert_id;

    // setting tags
    $postID  = $sql->insert_id;
    $tagsIDs = array();

    foreach($tags as $tag) array_push($tagsIDs, checkTag($tag));

    setPostTags($postID, $tagsIDs);

    return 0;
}

function setPostTags(int $postID, array $tagsIDs): int {
    global $MySQLDatabase;

    // Delete all tags from current file
    $sql = "DELETE FROM `PostsTags` WHERE `PostID` = $postID";
    $MySQLDatabase->query($sql);

    // insert new tags
    $sql = 'INSERT INTO `PostsTags` (`PostID`, `TagID`) VALUES';
    foreach($tagsIDs as $i => $tagID) $sql .= (" ($postID, $tagID)" . ((count($tagsIDs) - 1) == $i ? ';' : ','));

    $MySQLDatabase->query($sql);

    return 0;
}

function getAnime(array &$result, array $tags=[], int $limit=20, int $offset=0): int {
    global $MySQLDatabase;

    //if( !isSignedIn() ) return 1;
//
    //$userID = $_SESSION['migurdia']['userID'];

    $sql = "SELECT `anime`.`id`, `anime`.`title`, `anime`.`thumbnailUrl`                                         " .
           "FROM `anime`                                                " .
           "    LEFT JOIN `animeTags`ON `animeTags`.`animeId` = `anime`.`id` " .
           "    LEFT JOIN `tags` ON `animeTags`.`tagId`= `tags`.`id`         " .
           "GROUP BY `anime`.`id`                                       " ;

    if( !empty($tags) ){
        if( isset($tags['unwanted']) ){
            $unwantedTags = '(';
            $lastKey   = array_key_last($tags['unwanted']);

            foreach($tags['unwanted'] as $key => $tag){
                if ( !is_numeric($tag) ) continue;

                $unwantedTags .= $tag;
                $unwantedTags .= ($lastKey == $key) ?  ')' : ',';
            }

            $sql .= "HAVING (SUM(IF(`animeTags`.`tagID` IN $unwantedTags, 1, 0)) = 0)";
        }

        if( isset($tags['wanted']) ){
            $wantedTags = '(';
            $lastKey   = array_key_last($tags['wanted']);

            foreach($tags['wanted'] as $key => $tag){
                if( !is_numeric($tag) ) continue;

                $wantedTags .=  $tag;
                $wantedTags .= ($lastKey == $key) ?  ')' : ',';
            }

            $sql .= " ORDER BY SUM(IF(`animeTags`.`tagID` IN $wantedTags, 1, 0)) DESC, `anime`.`ID` ASC ";
        }else{
            $sql .= " ORDER BY `anime`.`ID` ASC ";
        }
    }else{
        $sql .= " ORDER BY `anime`.`ID` ASC ";
    }

    $sql .= " LIMIT $limit OFFSET $offset; ";

    $response = $MySQLDatabase->query($sql);

    if($response == false) return 1;

    while( $row = $response->fetch_assoc() ) array_push($result, $row);

    $response->free_result();

    return 0;
}

function getAnimeById(int $id){
    global $MySQLDatabase;

    $sql = "SELECT * FROM `anime` WHERE `id` = " . $id;

    $response  = $MySQLDatabase->query($sql);
    $r = $response->fetch_assoc();
    $response->free_result();

    return $r;
}

function getShindenId(array $anime){
    global $MySQLDatabase;

    if(strlen($anime['shindenId'])) return $anime['shindenId'];

    $url = researcher(
        "search?title=" . urlencode($anime['title']) .
        "&options=" . json_encode(array("type" => $anime['type'], "maxEps" => $anime['episodesCount'], "minEps" => $anime['episodesCount']))
    )[0]['url'];
    $id  = $anime['id'];

    $sql = "UPDATE `anime` SET `shindenId` = '$url' WHERE `id` = '$id';";

    if( !( $MySQLDatabase->query($sql) ) ) return null;

    return $url;
}

//-------------------------------\
//                               |
//         MAIN SECTION          |
//                               |
//-------------------------------/

# connection purpose
$cp = strtolower(requiredField('method'));

switch($cp){
    case 'signin':{
        $username = requiredField('username');
        $password = requiredField('password');

        switch ( signIn($username, $password) ) {
            case 0:  returnResult(array("SID" => session_id()))               ; break;
            case 1:  returnResult('Cannot find user with such name/email.', 2); break;
            case 2:  returnResult('Wrong password.'                       , 3); break;
            default: returnResult('Unknown.'                              , 1); break;
        }

        break;
    }
    case 'signup':{
        $username = requiredField('username'); // This will be checked, obviously
        $email    = requiredField('email'   ); // Checked only by build-in PHP function cuz I'm lazy
        $password = requiredField('password'); // I won't check if null, if someone doesn't want to have password, I don't care

        if( !filter_var($email, FILTER_VALIDATE_EMAIL) )
            returnResult('Invalid email address.', 4);

        switch ( signup($username, $email, $password) ) {
            case 0:  returnResult(array("SID" => session_id())); break;
            case 1:  returnResult('Username already taken.', 5); break;
            case 2:  returnResult('Email already taken.'   , 6); break;
            default: returnResult('Unknown.'               , 1); break;
        }

        break;
    }
    case 'getanimeepisodes': {
        $id = (int) requiredField('id');

        $anime     = getAnimeById($id);
        $shindenId = getShindenId($anime);

        returnResult(researcher("getepisodes?url=" . urlencode($shindenId)));
        
        break;
    }
    case 'getepisodeplayers': {
        $url = requiredField('url');
        returnResult(researcher("getepisodeplayers?url=" . urlencode($url)));
        break;
    }
    case 'getplayer': {
        $id = (int) requiredField('id');
        returnResult(researcher("getplayer?id=" . $id));
        break;
    }
    case 'getanimebyid': {
        $id = (int) requiredField('id');

        returnResult(getAnimeById($id));

        break;
    }
    case 'getanime':{
        $tags   = optionalField('tags', '[]');
        $amount = (int) optionalField('amount', 20);
        $offset = (int) optionalField('offset', 0);

        $tags   = json_decode($tags);
        $amount = ($amount > 100) ? 100 : $amount;

        if( !is_array($tags) ) $tags = array();

        $result   = array();
        $exitCode = getAnime($result, $tags, $amount, $offset);

        switch( $exitCode ){
            case  0: returnResult($result               , 0); break;
            case  1: returnResult('Session expired.'    , 7); break;
            default: returnResult('Something went wrong', 1); break;
        }

        break;
    }
    case 'getnameproposalsdb':{
        $hint   = requiredField('hint');
        $amount = optionalField('amount', 10);

        $result   = array();
        $exitCode = getNameProposals($result, $hint, $amount);

        returnResult($result);

        break;
    }
    case 'getnameproposalsonline':{
        $hint   = requiredField('hint');
        $amount = optionalField('amount', 10);
        
        $result   = searchAnimeMAL($hint);
        $exitCode = getNameProposals($result, $hint, $amount);

        returnResult($result);

        break;
    }
    case 'gettagproposals':{
        $hint   = requiredField('hint');
        $amount = optionalField('amount', 10);

        $result   = array();
        $exitCode = getTagProposals($result, $hint, $amount);

        returnResult($result);

        break;
    }
    case 'addposts':{
        $posts  = requiredField('posts');
        $posts  = json_decode ($posts, true);
        $result = array();

        foreach($posts as $post){
            if( !isset($post['fileUrl'     ]) ) { array_push($result, array('error')); continue; }
            if( !isset($post['thumbnailUrl']) ) { array_push($result, array('error')); continue; }
            if( !isset($post['tags'        ]) ) $post['tags'       ] = array();
            if( !isset($post['title'       ]) ) $post['title'      ] = 'Untitled';
            if( !isset($post['description' ]) ) $post['description'] = '';
            if( !isset($post['sourceUrl'   ]) ) $post['sourceUrl'  ] = NULL;

            $insertID = NULL;

            $success = addPost($insertID,
                $post['title'],
                $post['description'],
                $post['fileUrl'],
                $post['thumbnailUrl'],
                $post['tags'],
                $post['sourceUrl']
            );

            $tmp = array(
                'result'   => $insertID,
                'exitCode' => $success
            );

            array_push($result, $tmp);
        }

        returnResult($result);

        break;
    }
    case 'signout': { session_destroy(); returnResult([], 0); break; }
    default: { returnResult("unknown connection purpose('$cp').", 1); break; }
}

exit;
