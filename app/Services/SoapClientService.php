<?php

namespace App\Services;

use SoapClient;
use SoapFault;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class SoapClientService
{
    protected $client;
    protected $config;
    protected $lastRequest;
    protected $lastResponse;

    public function __construct()
    {
        $this->config = Config::get('soap.dss');
        // Don't initialize client during construction to avoid errors during package discovery
    }

    /**
     * Initialize SOAP client with configuration
     */
    protected function initializeClient()
    {
        // Check if SOAP extension is loaded
        if (!extension_loaded('soap')) {
            throw new \Exception('SOAP extension is not loaded. Please install php-soap extension or use Docker environment.');
        }

        try {
            $options = $this->config['soap_options'];

            // Note: Authentication is now handled via WSE headers in the call method
            // Basic auth can still be used as fallback if WSE is not available

            // Create stream context for SSL
            if (isset($options['stream_context'])) {
                $streamContextOptions = $options['stream_context'];
                $context = stream_context_create($streamContextOptions);
                $options['stream_context'] = $context;
            }

            $this->client = new SoapClient($this->config['wsdl_url'], $options);

            if (env('DETAILED_LOGGING'))
                $this->log('SOAP client initialized successfully', [
                    'wsdl_url' => $this->config['wsdl_url']
                ]);
        } catch (SoapFault $e) {
            $this->log('Failed to initialize SOAP client', [
                'error' => $e->getMessage(),
                'wsdl_url' => $this->config['wsdl_url']
            ], 'error');

            throw new \Exception('SOAP Client initialization failed: ' . $e->getMessage());
        }
    }

    /**
     * Ensure SOAP client is initialized
     */
    protected function ensureClientInitialized()
    {
        if ($this->client === null) {
            $this->initializeClient();
        }
    }

    /**
     * Get SOAP client functions
     */
    public function getFunctions()
    {
        try {
            $this->ensureClientInitialized();
            return $this->client ? $this->client->__getFunctions() : [];
        } catch (SoapFault $e) {
            $this->log('Failed to get SOAP functions', ['error' => $e->getMessage()], 'error');
            return [];
        } catch (\Exception $e) {
            $this->log('Failed to get SOAP functions', ['error' => $e->getMessage()], 'error');
            return [];
        }
    }

    /**
     * Get SOAP client types
     */
    public function getTypes()
    {
        try {
            $this->ensureClientInitialized();
            return $this->client ? $this->client->__getTypes() : [];
        } catch (SoapFault $e) {
            $this->log('Failed to get SOAP types', ['error' => $e->getMessage()], 'error');
            return [];
        } catch (\Exception $e) {
            $this->log('Failed to get SOAP types', ['error' => $e->getMessage()], 'error');
            return [];
        }
    }

    /**
     * Make a SOAP call
     */
    public function call($method, $parameters = [])
    {
        try {
            $this->ensureClientInitialized();

            // Set socket timeout to ensure calls don't hang
            $previousTimeout = ini_get('default_socket_timeout');
            $timeout = $this->config['soap_options']['stream_context']['http']['timeout'] ?? 10;
            ini_set('default_socket_timeout', (string)$timeout);

            if (env('DETAILED_LOGGING'))
                $this->log('Making SOAP call', [
                    'method' => $method,
                    'parameters' => $parameters,
                    'parameters_json' => json_encode($parameters, JSON_PRETTY_PRINT),
                    'timeout' => $timeout
                ]);

            $headers = [];

            // Add WSE authentication header if credentials are configured
            if (!empty($this->config['username']) && !empty($this->config['password'])) {
                $wsseHeader = $this->attachWSSUsernameToken($this->config['username'], $this->config['password']);
                if ($wsseHeader) {
                    $headers[] = $wsseHeader;
                    if (env('DETAILED_LOGGING'))
                        $this->log('Added WSE authentication header');
                }
            }

            // Add minimal DSS header
            $dssHeader = $this->createMinimalDSSHeader();
            if ($dssHeader) {
                $headers[] = $dssHeader;
            }

            if (!empty($headers)) {
                $this->client->__setSoapHeaders($headers);
            }

            $result = $this->client->__soapCall($method, [$parameters]);

            // Restore previous timeout
            ini_set('default_socket_timeout', $previousTimeout);

            // Store last request and response for debugging
            $this->lastRequest = $this->client ? $this->client->__getLastRequest() : null;
            $this->lastResponse = $this->client ? $this->client->__getLastResponse() : null;

            if (env('DETAILED_LOGGING'))
                $this->log('SOAP call successful', [
                    'method' => $method,
                    'result' => $result
                ]);

            return $result;
        } catch (SoapFault $e) {
            // Restore previous timeout on error
            if (isset($previousTimeout)) {
                ini_set('default_socket_timeout', $previousTimeout);
            }
            $this->lastRequest = $this->client ? $this->client->__getLastRequest() : null;
            $this->lastResponse = $this->client ? $this->client->__getLastResponse() : null;

            $this->log('SOAP call failed', [
                'method' => $method,
                'error' => $e->getMessage(),
                'faultcode' => $e->faultcode ?? null,
                'faultstring' => $e->faultstring ?? null,
                'request' => $this->sanitizeXmlForLogging($this->lastRequest),
                'response' => $this->sanitizeXmlForLogging($this->lastResponse)
            ], 'error');

            throw new \Exception('SOAP call failed: ' . $e->getMessage());
        }
    }

    /**
     * Get last SOAP request
     */
    public function getLastRequest()
    {
        return $this->lastRequest ?? ($this->client ? $this->client->__getLastRequest() : null);
    }

    /**
     * Get last SOAP response
     */
    public function getLastResponse()
    {
        return $this->lastResponse ?? ($this->client ? $this->client->__getLastResponse() : null);
    }

    /**
     * Get sanitized last SOAP request (safe for web display)
     */
    public function getSanitizedLastRequest()
    {
        $request = $this->getLastRequest();
        return $this->sanitizeXmlForLogging($request);
    }

    /**
     * Get sanitized last SOAP response (safe for web display)
     */
    public function getSanitizedLastResponse()
    {
        $response = $this->getLastResponse();
        return $this->sanitizeXmlForLogging($response);
    }

    /**
     * Get last request headers
     */
    public function getLastRequestHeaders()
    {
        return $this->client ? $this->client->__getLastRequestHeaders() : null;
    }

    /**
     * Get last response headers
     */
    public function getLastResponseHeaders()
    {
        return $this->client ? $this->client->__getLastResponseHeaders() : null;
    }

    /**
     * Attach a WS-Security UsernameToken header (PasswordText).
     */
    protected function attachWSSUsernameToken($username, $password)
    {
        $wsseNS = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';

        $usernameNode = new \SoapVar($username, XSD_STRING, null, $wsseNS, 'Username', $wsseNS);
        $passwordNode = new \SoapVar(
            $password,
            XSD_STRING,
            null,
            $wsseNS,
            'Password',
            $wsseNS
        );

        $usernameToken = new \SoapVar(
            [$usernameNode, $passwordNode],
            SOAP_ENC_OBJECT,
            null,
            $wsseNS,
            'UsernameToken',
            $wsseNS
        );

        $security = new \SoapVar(
            [$usernameToken],
            SOAP_ENC_OBJECT,
            null,
            $wsseNS,
            'Security',
            $wsseNS
        );

        $header = new \SoapHeader($wsseNS, 'Security', $security, true);
        return $header;
    }

    /**
     * Create minimal DSS SOAP header with only required field
     */
    protected function createMinimalDSSHeader()
    {
        try {
            // Only the absolutely required field according to XSD
            $headerData = [
                'CreateDateTime' => date('c') // ISO 8601 format - this is the only required field
            ];

            if (env('DETAILED_LOGGING'))
                $this->log('Creating minimal DSS header', ['headerData' => $headerData]);

            // Create SoapHeader with the correct namespace from WSDL
            $header = new \SoapHeader(
                'http://api.socialservices.gov.au/Common', // from xmlns:common in WSDL
                'Header', // element name
                $headerData,
                false // mustUnderstand
            );

            return $header;
        } catch (\Exception $e) {
            $this->log('Failed to create minimal DSS header', ['error' => $e->getMessage()], 'error');
            return null;
        }
    }

    /**
     * Filter sensitive data from parameters for logging
     */
    protected function filterSensitiveData($data)
    {
        if (is_array($data)) {
            $filtered = [];
            foreach ($data as $key => $value) {
                if (is_string($key) && in_array(strtolower($key), ['password', 'username', 'credentials'])) {
                    $filtered[$key] = '***MASKED***';
                } elseif (is_array($value) || is_object($value)) {
                    $filtered[$key] = $this->filterSensitiveData($value);
                } else {
                    $filtered[$key] = $value;
                }
            }
            return $filtered;
        }
        return $data;
    }

    /**
     * Sanitize XML content by masking credentials
     */
    protected function sanitizeXmlForLogging($xml)
    {
        if (!$xml) return $xml;

        // Mask password content in WSE headers
        $xml = preg_replace(
            '/<[^>]*:Password[^>]*>.*?<\/[^>]*:Password>/i',
            '<ns4:Password>***MASKED***</ns4:Password>',
            $xml
        );

        // Mask username content in WSE headers
        $xml = preg_replace(
            '/<[^>]*:Username[^>]*>.*?<\/[^>]*:Username>/i',
            '<ns4:Username>***MASKED***</ns4:Username>',
            $xml
        );

        return $xml;
    }

    /**
     * Log messages if logging is enabled
     */
    protected function log($message, $context = [], $level = 'info')
    {
        if ($this->config['logging']['enabled']) {
            // Filter sensitive data from context
            $sanitizedContext = $this->filterSensitiveData($context);

            Log::channel($this->config['logging']['channel'])
                ->{$level}('[DSS SOAP] ' . $message, $sanitizedContext);
        }
    }

    /**
     * Test connection to SOAP service
     */
    public function testConnection()
    {
        try {
            $functions = $this->getFunctions();
            $types = $this->getTypes();

            return [
                'status' => 'success',
                'message' => 'Connection successful',
                'functions_count' => count($functions),
                'types_count' => count($types),
                'functions' => $functions,
                'types' => $types
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
}
