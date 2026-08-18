<?php
// Permite o acesso de qualquer origem. Se quiser restringir, troque '*' por 'http://localhost:4200'
header("Access-Control-Allow-Origin: http://localhost:4200");
//header("Access-Control-Allow-Origin: http://localhost:8080/*");
//header("Access-Control-Allow-Origin: http://localhost:3000");

// Define os métodos HTTP permitidos
header("Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS");

// Define os cabeçalhos que o frontend pode enviar (como tokens de autenticação)
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With,api_key");

// Intercepta a requisição preflight e retorna sucesso imediato sem carregar o resto do framework
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}
header('Content-Type: application/json; charset=utf-8');

// initialization script
require_once 'init.php';

class AdiantiRestServer
{
    public static function run($request)
    {
        $ini      = AdiantiApplicationConfig::get();
        $input    = json_decode(file_get_contents("php://input"), true);
        $request  = array_merge($request, (array) $input);
        $class    = isset($request['class']) ? $request['class']   : '';
        $method   = isset($request['method']) ? $request['method'] : '';
        $headers  = AdiantiCoreApplication::getHeaders();
        $response = NULL;

        $headers['Authorization'] = $headers['Authorization'] ?? ($headers['authorization'] ?? null); // for clientes that send in lowercase (Ex. futter)

        try {
            if (empty($headers['Authorization'])) {
                throw new Exception(_t('Authorization error'));
            } else {
                if (substr($headers['Authorization'], 0, 5) == 'Basic') {
                    if (class_exists($class) && defined("{$class}::REST_KEY") and (!empty($class::REST_KEY))) {
                        if ($class::REST_KEY !== substr($headers['Authorization'], 6)) {
                            http_response_code(401);
                            return json_encode(array('status' => 'error', 'data' => _t('Authorization error')));
                        }
                    } else {
                        if (empty($ini['general']['rest_key'])) {
                            throw new Exception(_t('REST key not defined'));
                        }

                        if ($ini['general']['rest_key'] !== substr($headers['Authorization'], 6)) {
                            http_response_code(401);
                            return json_encode(array('status' => 'error', 'data' => _t('Authorization error')));
                        }
                    }
                } else if (substr($headers['Authorization'], 0, 6) == 'Bearer') {
                    ApplicationAuthenticationService::fromToken(substr($headers['Authorization'], 7));
                } else {
                    http_response_code(403);
                    throw new Exception(_t('Authorization error'));
                }
            }

            $response = AdiantiCoreApplication::execute($class, $method, $request, 'rest');
            if (is_array($response)) {
                array_walk_recursive($response, ['AdiantiStringConversion', 'assureUnicode']);
            }
            return json_encode(array('status' => 'success', 'data' => $response));
        } catch (Exception $e) {
            if (200 === http_response_code()) {
                http_response_code(500);
            }

            return json_encode(array('status' => 'error', 'data' => $e->getMessage()));
        } catch (Error $e) {
            if (200 === http_response_code()) {
                http_response_code(500);
            }

            return json_encode(array('status' => 'error', 'data' => $e->getMessage()));
        }
    }
}

print AdiantiRestServer::run($_REQUEST);
