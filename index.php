<?php
declare (strict_types=1);

ini_set("log_errors", "on");
ini_set("error_log", "./php-error.log");

function getHeader()
{
    $out = [];
    if (php_sapi_name() == 'cli') {
    } else {
        $headers = apache_request_headers();
        foreach ($headers as $key => $value) {
            $out[$key] = $value;
        }
    }
    return $out;
}

function getFirstContentType(string $rawContentType): string
{
    $tConTyp = array_map(
        function (string $i) {
            return trim($i);
        },
        explode(',', $rawContentType)
    );
    return strtolower($tConTyp[0] ?? '');
}

function decodeBody()
{
    $rawBody = file_get_contents('php://input');
    $rawContentType = $_SERVER['HTTP_CONTENT_TYPE'] ?? $_SERVER['CONTENT_TYPE'] ?? '';
    $contentType = getFirstContentType($rawContentType);
    switch ($contentType) {
        case 'application/json':
            $body = json_decode($rawBody);
            break;
        case 'application/x-www-form-urlencoded':
        case 'multipart/form-data':
            $body = $_POST;
            break;
        default:
            $body = $rawBody;
    }
    return $body;
}

function getBody()
{
    $out = "";
    if (PHP_SAPI === 'cli') {
        // Pour l'instant on ne fait rien
    } else {
        if ($_SERVER['REQUEST_METHOD'] != "GET" && $_SERVER['REQUEST_METHOD'] != "HEAD") {
            $out .= decodeBody();
        }
    }
    return $out;
}

function getRequest()
{
    $out = [];
    if (isset($_SERVER['SERVER_PROTOCOL'])) {
        $out['PROTOCOL'] = $_SERVER['SERVER_PROTOCOL'];
    }
    $out['HOST'] = ($_SERVER['SERVER_NAME'] ?? '') . (isset($_SERVER['SERVER_PORT']) ? ':' . $_SERVER['SERVER_PORT'] : '');
    if (isset($_SERVER['REQUEST_METHOD'])) {
        $out['METHOD'] = $_SERVER['REQUEST_METHOD'];
    }
    if (isset($_SERVER['REQUEST_URI'])) {
        $out['URI'] = $_SERVER['REQUEST_URI'];
    }
    $out['FROM'] = ($_SERVER['REMOTE_ADDR'] ?? '') . (isset($_SERVER['REMOTE_PORT']) ? ':' . $_SERVER['REMOTE_PORT'] : '');
    //$out['extra'] = $_SERVER;
    return $out;
}

class Request
{
    public function __construct()
    {
        $this->request = getRequest();
        $this->headers = getHeader();
        $this->body = getBody();
    }
}

function getInfoFromRequest()
{
    return new Request();
}

function readableRequest(Request $request)
{
    $rReqest = '';
    foreach ($request->request as $k => $v) {
        $rReqest .= $k . ': ' . $v . ' ';
    }
    $rHead = '';
    foreach ($request->headers as $k => $v) {
        if (is_scalar($v)) {
            $rHead .= $k . ': ' . $v . PHP_EOL;
        } elseif (is_array($v)) {
            $rHead .= $k . ': ' . implode(', ', $v) . PHP_EOL;
        } else {
            $rHead .= $k . ': ' . json_encode($v, JSON_PRETTY_PRINT) . PHP_EOL;
        }
    }
    $rBody = '';
    if (is_scalar($request->body)) {
        $rBody .= $request->body;
    } else {
        $rBody .= json_encode($request->body, JSON_PRETTY_PRINT) . ' ';
    }
    return $rReqest . PHP_EOL . PHP_EOL . $rHead . PHP_EOL . $rBody;
}

function echoResponse($request)
{
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: content-type');
    if (empty($_GET)) {
        header('Content-Type: application/json');
        echo json_encode($request);
    } else {
        header('Content-Type: text/plain');
        echo readableRequest($request);
    }
}

$request = getInfoFromRequest();
echoResponse($request);
file_put_contents(__DIR__ . '/last', json_encode($request));
