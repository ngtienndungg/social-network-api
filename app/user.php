<?php
if (isset($app)) {
    $app->post('/login', function ($request, $response, $args) {
        require_once __DIR__ . '/../bootstrap/dbConnection.php';
        $output = array();
        $requestData = array();

        $requestData['uid'] = $request->getParsedBody()['uid'];
        $requestData['name'] = $request->getParsedBody()['name'];
        $requestData['email'] = $request->getParsedBody()['email'];
        $requestData['profileUrl'] = $request->getParsedBody()['profileUrl'];
        $requestData['coverUrl'] = $request->getParsedBody()['coverUrl'];
        $requestData['userToken'] = $request->getParsedBody()['userToken'];

        if (isset($pdo)) {
            $query = $pdo->prepare("SELECT `uid` FROM `user` WHERE `uid` = :uid LIMIT 1");
            $query->bindparam(':uid', $requestData['uid']);
            $query->execute();
            $errorData = $query->errorInfo();
            if ($errorData[1]) {
                return checkError($response, $errorData);
            }
            $count = $query->rowCount();

            if ($count == 1) {
                $query = $pdo->prepare("UPDATE `user` SET `name` = :name, `email` = :email, `profileUrl`= :profileUrl,
                                            `coverUrl` = :coverUrl, `userToken` = :userToken WHERE `uid` = :uid");
            } else {
                $query = $pdo->prepare("INSERT INTO `user` (`uid`, `name`, `email`, `profileUrl`, `coverUrl`, `userToken`)
                                            VALUES (:uid, :name, :email, :profileUrl, :coverUrl, :userToken)");
            }

            $query->execute($requestData);
            $errorData = $query->errorInfo();
            if ($errorData[1]) {
                return checkError($response, $errorData);
            }
            $output['status'] = 200;
            $output['message'] = "Login Success";
            $output['auth'] = $requestData;

            $payload = json_encode($output);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        }
    });

    $app->get('/loadprofileinfo', function ($request, $response, $args) {
        require_once __DIR__ . '/../bootstrap/dbConnection.php';

        /*
         current_state
         1 = We are friends
         2 = we have sent friend request to that person
         3 = We have received friend request from that person
         4 = We are unknown
         5 = Our own profile
      */

        $output = array();
        $userId = $request->getQueryParams()['userId'];
        $state = 0;

        if (isset($request->getQueryParams()['current_state'])) {
            $state = $request->getQueryParams()['current_state'];
        } else {
            $profileId = $request->getQueryParams()['profileId'];

            $request = checkRequest($userId, $profileId);
            if ($request) {
                if ($request['sender'] == $userId) {
                    // we have send the request
                    $state = "2";
                } else {
                    $state = "3";
                    //we have received the request
                }
            } else {
                if (checkFriend($userId, $profileId)) {
                    $state = "1";
                    //we are friends
                } else {
                    $state = "4";
                    //we are unknown to one another
                }
            }
            $userId = $profileId;
        }

        if (isset($pdo)) {
            $query = $pdo->prepare('SELECT * FROM `user` WHERE `uid` = :userId');
            $query->bindParam(':userId', $userId, PDO::PARAM_STR);
            $query->execute();

            $errorData = $query->errorInfo();
            if ($errorData[1]) {
                return checkError($response, $errorData);
            }

            $result = $query->fetch(PDO::FETCH_ASSOC);

            $result['state'] = $state;
            $output['status'] = 200;
            $output['message'] = "Profile Data Retrieved";
            $output['profile'] = $result;

            $payload = json_encode($output);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        }

    });

    $app->post('/uploadimage', function ($request, $response, $args) {
        include __DIR__ . '/../bootstrap/dbConnection.php';

        $uid = $request->getParsedBody()['uid'];
        $isCoverImage = $request->getParsedBody()['isCoverImage'];

        if (move_uploaded_file($_FILES['file']["tmp_name"], "../uploads/" . $_FILES["file"]["name"])) {
            $msg = "";
            if ($isCoverImage == 'true') {
                $query = "UPDATE `user` SET `coverUrl` = :uploadUrl WHERE `uid` = :uid";
                $msg = "Cover upload successfully";
            } else {
                $query = "UPDATE `user` SET `profileUrl` = :uploadUrl WHERE `uid` = :uid";
                $msg = "Avatar upload successfully";
            }

            $imageLocation = "../uploads/" . $_FILES["file"]["name"];
            if (isset($pdo)) {
                $query = $pdo->prepare($query);
                $query->bindParam(':uid', $uid, PDO::PARAM_STR);
                $query->bindParam(':uploadUrl', $imageLocation, PDO::PARAM_STR);
                $query->execute();

                $errorData = $query->errorInfo();
                if ($errorData[1]) {
                    return checkError($response, $errorData);
                }

                $output['status'] = 200;
                $output['message'] = $msg;
                $output['extra'] = $imageLocation;

                $payload = json_encode($output);
                $response->getBody()->write($payload);
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            }
        } else {
            $output['status'] = 500;
            $output['message'] = "Can not upload image to server";

            $payload = json_encode($output);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    $app->get('/search', function ($request, $response, $args) {
        include __DIR__ . '/../bootstrap/dbConnection.php';

        $keyword = $request->getQueryParams()['keyword'];

        if (isset($pdo)) {
            $query = $pdo->prepare("SELECT * FROM `user` WHERE `name` LIKE '$keyword%' LIMIT 10");
            $query->execute();

            $errorData = $query->errorInfo();
            if ($errorData[1]) {
                return checkError($response, $errorData);
            }

            $result = $query->fetchAll(PDO::FETCH_ASSOC);

            $output['status'] = 200;
            $output['message'] = "Search";
            $output['user'] = $result;

            $payload = json_encode($output);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        }
    });

    function checkRequest($userId, $profileId)
    {
        include __DIR__ . '/../bootstrap/dbConnection.php';
        if (isset($pdo)) {
            $stmt = $pdo->prepare("SELECT * FROM `requests` WHERE `sender` = :userId AND `receiver` = :profileId 
            OR `sender` = :profileId AND `receiver` = :userId");
            $stmt->bindParam(':userId', $userId, PDO::PARAM_STR);
            $stmt->bindParam(':profileId', $profileId, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    function checkFriend($userId, $profileId)
    {
        include __DIR__ . '/../bootstrap/dbConnection.php';
        if (isset($pdo)) {
            $stmt = $pdo->prepare("SELECT * FROM `friends` WHERE `userId` = :userId AND `profileId` = :profileId");
            $stmt->bindParam(':userId', $userId, PDO::PARAM_STR);
            $stmt->bindParam(':profileId', $profileId, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    $app->get('/getnewsfeed', function ($request, $response, $args) {

        include __DIR__ . '/../bootstrap/dbConnection.php';

        $uid = $request->getQueryParams()['uid'];
        $limit = $request->getQueryParams()['limit'];
        $offset = $request->getQueryParams()['offset'];

        if (isset($pdo)) {
            $query = $pdo->prepare("
                               SELECT 	 posts.*, user.*
                               from 	`timeline`
                               INNER JOIN `posts`
                                   on timeline.postId = posts.postId
                               INNER JOIN `user`
                                   on  posts.postUserId = user.uid
                               WHERE 	timeline.whoseTimeLine= :uid
                               ORDER By timeline.statusTime DESC
                               LIMIT $limit OFFSET $offset
                               "
            );
            $query->bindParam(':uid', $uid, PDO::PARAM_STR);
            $query->execute();

            $errorData = $query->errorInfo();
            if ($errorData[1]) {
                return checkError($response, $errorData);
            }
            $posts = $query->fetchAll(PDO::FETCH_OBJ);
            $output['status'] = 200;
            $output['message'] = "Newsfeed Loaded Successfully";
            $output['posts'] = $posts;

            $payload = json_encode($output);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        }
    });

    $app->get('/loadprofileposts',function($request,  $response,  $args){

        include __DIR__ . '/../bootstrap/dbConnection.php';

        $output = array();
        $uid = $request->getQueryParams()['uid'];
        $limit = $request->getQueryParams()['limit'];
        $offset = $request->getQueryParams()['offset'];

        $current_state = $request->getQueryParams()['current_state'];

        if (isset($pdo)) {
            $query =  $pdo->prepare("SELECT * from `user` WHERE `uid` = :uid LIMIT 1");
            $query->bindParam(':uid', $uid, PDO::PARAM_STR);
            $query->execute();

            $errorData = $query->errorInfo();
            if($errorData[1]){
                return checkError($response, $errorData);
            }

            $userInfo =$query->fetch(PDO::FETCH_OBJ);

            /*

            privacy flags representation

                0 - > Friends privacy level
                1 - > Only Me privacy level
                2 - > Public privacy level

            */


            /*
                Relations between two accounts

                1 =  two people are friends
                4 = people are unkown
                5 = own profile


            */

            if($current_state==5){

                /*

                    -> our own profile,
                    -> can view only me, friends and public  privacy level post

                */


                $query = " SELECT * FROM `posts` WHERE `postUserId` = :uid ORDER By statusTime DESC";



            }else if($current_state==4){

                /*

                     -> unknown profile
                     -> can view public privacy level post

                 */

                $query = " SELECT * FROM `posts` WHERE `postUserId` = :uid AND `privacy` = 2 ORDER By statusTime DESC";


            }else if($current_state==1){

                $query = " SELECT * FROM `posts` WHERE `postUserId` = :uid AND ( `privacy` = 2 OR `privacy` = 0 ) ORDER By statusTime DESC";

                /*

                    -> friends account
                    -> can view fiends and public privacy level post

                */
            }else{
                $query = " SELECT * FROM `posts` WHERE `postUserId` = :uid AND `privacy` = 2 ORDER By statusTime DESC";
                /*
                    -> relation not known
                    -> can view public privacy level post

                */
            }

            $query .=  '  LIMIT '.$limit. ' OFFSET '.$offset;
            $query = $pdo->prepare($query);
            $query->bindParam(':uid', $uid, PDO::PARAM_STR);
            $query->execute();

            $errorData = $query->errorInfo();
            if($errorData[1]){
                return checkError($response, $errorData);
            }

            $posts= $query->fetchAll(PDO::FETCH_OBJ);

            foreach ($posts as $key => $value) {
                $value->name         =  $userInfo->name;
                $value->profileUrl   =  $userInfo->profileUrl;
                $value->email        =  $userInfo->email;
                $value->coverUrl     =  $userInfo->coverUrl;
                //for reaction feature
                $reactionCheck = checkOurReact($uid,  $value->postId);
                if($reactionCheck){
                    $value->reactionType=$reactionCheck->reactionType;
                }else{
                    $value->reactionType="default";
                }
            }

            $output['status']  = 200;
            $output['message'] = "Profile post Loaded Successfully";
            $output['posts'] = $posts;


            $payload = json_encode($output,JSON_NUMERIC_CHECK);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        }

    });

    $app->post('/performreaction',function($request, $response,  $args){


        include __DIR__ .'/../bootstrap/dbConnection.php';

        $userId = $request->getParsedBody()['userId'];
        $postId =  $request->getParsedBody()['postId'];
        $postOwnerId =  $request->getParsedBody()['postOwnerId'];
        $previousReactionType = $request->getParsedBody()['previousReactionType'];
        $newReactionType = $request->getParsedBody()['newReactionType'];
        $message = "Operation Successful";

        /*
            (previousReactionType , newReactionType)
            like, love, care, haha, wow, sad, angry, default

            default -> used to undo reactions
        */

        $oldReactionColumn = checkColumnName($previousReactionType);
        $newReactionColumn = checkColumnName($newReactionType);

        if($newReactionType == "default"){

            if($previousReactionType == "default"){
                $message = "No Operation Performed";
                $reactions = getReactionCount($postId);
                $reactions->reactionType = 'default';

                $output['status']  = 200;
                $output['message'] = $message;
                $output['reaction'] = $reactions;

                $payload = json_encode($output,JSON_NUMERIC_CHECK);
                $response->getBody()->write($payload);
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

            }
            // decrease counter of old reaction
            if (isset($pdo)) {
                $stmt = $pdo->prepare(" UPDATE `posts` 
                                    SET ". $oldReactionColumn ." = " . $oldReactionColumn ." -1 "." 
                                    WHERE `postId` = :postId");
                $stmt->bindParam(":postId", $postId, PDO::PARAM_INT);
                $stmt->execute();

                $errorData = $stmt->errorInfo();
                if($errorData[1]){
                    return checkError($response, $errorData);
                }

                // remove old reaction from reaction table
                $stmt = $pdo->prepare( "DELETE FROM `reactions` WHERE
                                `reactionBy` = :userId AND
                                `postOn` = :postId 
                             ");

                $stmt->bindParam(":userId", $userId, PDO::PARAM_STR);
                $stmt->bindParam(":postId", $postId, PDO::PARAM_INT);
                $stmt->execute();

                $errorData = $stmt->errorInfo();
                if($errorData[1]){
                    return checkError($response, $errorData);
                }
                $message = "Reaction Undo Successfull";
            }
        }else{
            // previous = care, newReaction = wow
            if($previousReactionType != "default"){
                // decrease counter of old reaction
                if (isset($pdo)) {
                    $stmt = $pdo->prepare(" UPDATE `posts` 
            SET ". $oldReactionColumn ." = " . $oldReactionColumn ." -1 "." 
            WHERE `postId` = :postId");
                    $stmt->bindParam(":postId", $postId, PDO::PARAM_INT);
                    $stmt->execute();

                    $errorData = $stmt->errorInfo();
                    if($errorData[1]){
                        return checkError($response, $errorData);
                    }

                    // remove old reaction from reaction table
                    $stmt = $pdo->prepare( "DELETE FROM `reactions` WHERE
        `reactionBy` = :userId AND
        `postOn` = :postId 
        ");

                    $stmt->bindParam(":userId", $userId, PDO::PARAM_STR);
                    $stmt->bindParam(":postId", $postId, PDO::PARAM_INT);
                    $stmt->execute();

                    $errorData = $stmt->errorInfo();
                    if($errorData[1]){
                        return checkError($response, $errorData);
                    }
                }
            }


            // increase counter of new reaction
            $stmt = $pdo->prepare(" UPDATE `posts` 
                            SET ". $newReactionColumn ." = " . $newReactionColumn ." +1 "." 
                            WHERE `postId` = :postId");



            $stmt->bindParam(":postId", $postId, PDO::PARAM_INT);
            $stmt->execute();

            $errorData = $stmt->errorInfo();
            if($errorData[1]){
                return checkError($response, $errorData);
            }

            // insert new reaction to reactions table
            $stmt = $pdo->prepare( "INSERT INTO `reactions` 
                                (`reactionBy`, `postOn`, `reactionType` ) 
                                VALUES (:reactionBy, :postOn, :reactionType); ");


            $stmt->bindParam(':reactionBy', $userId, PDO::PARAM_STR);
            $stmt->bindParam(':postOn', $postId, PDO::PARAM_INT);
            $stmt->bindParam(':reactionType', $newReactionType, PDO::PARAM_STR);

            $stmt->execute();

            $errorData = $stmt->errorInfo();
            if($errorData[1]){
                return checkError($response, $errorData);
            }
            $message = "Reaction changed from ".$previousReactionType. " to ".$newReactionType;
        }
        // send back the updated reaction counts
        $reactions = getReactionCount($postId);
        $reactions->reactionType = $newReactionType;

        $output['status']  = 200;
        $output['message'] = $message;
        $output['reaction'] = $reactions;

        $payload = json_encode($output,JSON_NUMERIC_CHECK);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    });
    function checkColumnName($reactionType)
    {
        $columnName = "likeCount";
        if ($reactionType == "love") {

            $columnName = "loveCount";

        } else if ($reactionType == "care") {

            $columnName = "careCount";

        } else if ($reactionType == "haha") {

            $columnName = "hahaCount";

        } else if ($reactionType == "wow") {

            $columnName = "wowCount";

        } else if ($reactionType == "sad") {

            $columnName = "sadCount";

        } else if ($reactionType == "angry") {

            $columnName = "angryCount";

        }
        return $columnName;
    }

    function getReactionCount($postId){

        include __DIR__ . '/../bootstrap/dbConnection.php';

        if (isset($pdo)) {
            $stmt =  $pdo->prepare("  SELECT 
                                 `likeCount` , `loveCount`, `careCount`,
                                 `hahaCount`, `wowCount`, `sadCount`, `angryCount`
                                  from `posts` WHERE `postId` = :postId LIMIT 1");
            $stmt->bindParam(':postId', $postId, PDO::PARAM_STR);
            $stmt->execute();

            $errorData = $stmt->errorInfo();

            $userInfo =$stmt->fetch(PDO::FETCH_OBJ);
            return $userInfo;
        }
    }
    function checkOurReact($userId,$postId){
        include __DIR__ . '/../bootstrap/dbConnection.php';
        if (isset($pdo)) {
            $stmt = $pdo->prepare("SELECT * FROM `reactions` WHERE `reactionBy` = :userId AND `postOn` = :postId");
            $stmt->bindParam(":userId", $userId, PDO::PARAM_STR);
            $stmt->bindParam(":postId", $postId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_OBJ);
        }
    }
}