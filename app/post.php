<?php
if (isset($app)) {
    $app->post('/uploadpost', function ($request, $response, $args) {
        include __DIR__ . '/../bootstrap/dbConnection.php';

        $requestData = array();
        $output = array();

        $requestData['post'] = $request->getParsedBody()['post'];
        $requestData['postUserId'] = $request->getParsedBody()['postUserId'];
        $requestData['privacy'] = $request->getParsedBody()['privacy'];
        $requestData['statusImage'] = "";

        if (isset($_FILES['file']['tmp_name'])) {
            if (move_uploaded_file($_FILES['file']["tmp_name"], "../uploads/" . $_FILES["file"]["name"])) {
                $requestData['statusImage'] = "../uploads/" . $_FILES["file"]["name"];
            } else {
                $output['status'] = 500;
                $output['message'] = "Couldn't upload image to server";

                $payload = json_encode($output);
                $response->getBody()->write($payload);

                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }
        }
        if (isset($pdo)) {
            $query = $pdo->prepare("INSERT INTO `posts`(`post`, `postUserId`, `statusImage`, `statusTime`, privacy)
                                    VALUES (:post, :postUserId, :statusImage, current_timestamp, :privacy)");
            $query->execute($requestData);
            $errorData = $query->errorInfo();
            if ($errorData[1]) {
                return checkError($response, $errorData);
            }
            $output['status'] = 200;
            $output['message'] = "Upload Successfully";

            $payload = json_encode($output);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        }
    });
}
?>