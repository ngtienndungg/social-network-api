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

        $output = array();
        $userId = $request->getQueryParams()['userId'];
        $state = 0;

        if (isset($request->getQueryParams()['current_state'])) {
            $state = $request->getQueryParams()['current_state'];
        } else {

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
            $output['message'] = "Profile data is retrieve";
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
}

    function checkRequest($userId,$profileId){
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

    function checkFriend($userId,$profileId){
        include __DIR__ . '/../bootstrap/dbConnection.php';
        if (isset($pdo)) {
            $stmt = $pdo->prepare("SELECT * FROM `friends` WHERE `userId` = :userId AND `profileId` = :profileId");
            $stmt->bindParam(':userId', $userId, PDO::PARAM_STR);
            $stmt->bindParam(':profileId', $profileId, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
?>