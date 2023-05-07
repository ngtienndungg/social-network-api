<?php
if (isset($app)) {
    $app->post('/login', function ($request, $response, $args) {
        require_once __DIR__ .'/../bootstrap/dbConnection.php';
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
            $count = $query->rowCount();

            if ($count == 1) {
                $query = $pdo->prepare("UPDATE `user` SET `name` = :name, `email` = :email, `profileUrl`= :profileUrl,
                                            `coverUrl` = :coverUrl, `userToken` = :userToken WHERE `uid` = :uid");
                $query->execute($requestData);
            } else {
                $query = $pdo->prepare("INSERT INTO `user` (`uid`, `name`, `email`, `profileUrl`, `coverUrl`, `userToken`)
                                            VALUES (:uid, :name, :email, :profileUrl, :coverUrl, :userToken)");
                $query->execute($requestData);
            }

            $output['status'] = 200;
            $output['message'] = "Login Success";
            $output['auth'] = $requestData;

            $payload = json_encode($output);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        }
    });
}
?>