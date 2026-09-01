<?php

namespace App\Http\Controllers;

use App\Models\InboundTemplate;
use App\Models\Pannel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class InboundTemplateController extends Controller
{
    /**
     * Create a new inbound template from user input
     */
    public function createFromUserInput(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'pannel_id' => 'required|exists:pannels,id',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'user_input' => 'required|string', // User provided inbound configuration
                'created_by' => 'nullable|exists:users,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            // Parse user input to extract configuration
            $parsedConfig = $this->parseUserInput($request->user_input);
            
            if (!$parsedConfig) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid inbound configuration format'
                ], 400);
            }

            // Determine config type
            $configType = $this->determineConfigType($parsedConfig);

            // Create template
            $template = InboundTemplate::create([
                'pannel_id' => $request->pannel_id,
                'name' => $request->name,
                'description' => $request->description,
                'inbound_config' => $parsedConfig,
                'protocol' => $parsedConfig['protocol'],
                'port' => $parsedConfig['port'],
                'stream_settings' => $parsedConfig['streamSettings'] ?? null,
                'settings' => $parsedConfig['settings'] ?? null,
                'listen' => $parsedConfig['listen'] ?? null,
                'server_info' => $parsedConfig['server_info'] ?? null,
                'dns_info' => $parsedConfig['dns_info'] ?? null,
                'routing_info' => $parsedConfig['routing_info'] ?? null,
                'remarks' => $parsedConfig['remarks'] ?? null,
                'config_type' => $configType,
                'is_active' => true,
                'created_by' => $request->created_by
            ]);

            Log::info("Inbound template created", [
                'template_id' => $template->id,
                'panel_id' => $request->pannel_id,
                'protocol' => $parsedConfig['protocol']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Template created successfully',
                'data' => $template
            ]);

        } catch (\Exception $e) {
            Log::error('Create template error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Server error occurred'
            ], 500);
        }
    }

    /**
     * Parse user input to extract inbound configuration
     */
    private function parseUserInput(string $userInput): ?array
    {
        try {
            // Try to parse as JSON first
            if (str_starts_with(trim($userInput), '{')) {
                $config = json_decode($userInput, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $this->parseV2RayConfig($config);
                }
            }

            // Try to parse as URL format (vless://, vmess://, trojan://)
            if (preg_match('/^(vless|vmess|trojan):\/\/(.+)$/i', $userInput, $matches)) {
                return $this->parseUrlFormat($userInput);
            }

            // Try to parse as base64 encoded VMESS
            if (str_starts_with($userInput, 'vmess://')) {
                return $this->parseVmessUrl($userInput);
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Parse user input error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Parse V2Ray configuration file
     */
    private function parseV2RayConfig(array $config): ?array
    {
        try {
            // Check if this is a V2Ray config with inbounds
            if (isset($config['inbounds']) && is_array($config['inbounds'])) {
                $inbounds = $config['inbounds'];
                
                // Find the main inbound (usually the first one)
                $mainInbound = null;
                foreach ($inbounds as $inbound) {
                    if (isset($inbound['protocol']) && in_array($inbound['protocol'], ['socks', 'http', 'shadowsocks', 'vmess', 'vless', 'trojan'])) {
                        $mainInbound = $inbound;
                        break;
                    }
                }

                if ($mainInbound) {
                    return $this->extractInboundFromV2Ray($mainInbound, $config);
                }
            }

            // Check if this is a single inbound configuration
            if (isset($config['protocol'])) {
                return $this->extractInboundFromV2Ray($config, $config);
            }

            // Check if this is a Hysteria2 configuration
            if (isset($config['server']) && isset($config['auth'])) {
                return $this->parseHysteria2Config($config);
            }

            // Check if this is a ShadowSocks2022 configuration
            if (isset($config['outbounds']) && $this->hasShadowSocks2022($config)) {
                return $this->parseShadowSocks2022Config($config);
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Parse V2Ray config error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if configuration has ShadowSocks2022
     */
    private function hasShadowSocks2022(array $config): bool
    {
        if (!isset($config['outbounds']) || !is_array($config['outbounds'])) {
            return false;
        }

        foreach ($config['outbounds'] as $outbound) {
            if (isset($outbound['protocol']) && $outbound['protocol'] === 'shadowsocks') {
                if (isset($outbound['settings']['servers'][0]['method'])) {
                    $method = $outbound['settings']['servers'][0]['method'];
                    if (str_contains($method, '2022-')) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Parse ShadowSocks2022 configuration
     */
    private function parseShadowSocks2022Config(array $config): array
    {
        $outbound = null;
        $inbound = null;

        // Find ShadowSocks2022 outbound
        foreach ($config['outbounds'] as $out) {
            if (isset($out['protocol']) && $out['protocol'] === 'shadowsocks') {
                $outbound = $out;
                break;
            }
        }

        // Find main inbound
        if (isset($config['inbounds'][0])) {
            $inbound = $config['inbounds'][0];
        }

        if (!$outbound) {
            return $this->createDefaultConfig($config);
        }

        $server = $outbound['settings']['servers'][0] ?? [];
        $address = $server['address'] ?? '';
        $port = (int) ($server['port'] ?? 443);
        $method = $server['method'] ?? '';
        $password = $server['password'] ?? '';

        // Extract DNS information
        $dnsInfo = $this->extractDNSInfo($config);

        // Extract routing information
        $routingInfo = $this->extractRoutingInfo($config);

        return [
            'id' => uniqid('template_'),
            'protocol' => 'shadowsocks2022',
            'port' => $port,
            'settings' => [
                'method' => $method,
                'password' => $password,
                'level' => $server['level'] ?? 0
            ],
            'streamSettings' => $outbound['streamSettings'] ?? [],
            'tag' => $outbound['tag'] ?? 'shadowsocks2022',
            'listen' => $inbound['listen'] ?? '127.0.0.1',
            'server_info' => [
                'address' => $address,
                'port' => $port,
                'protocol' => 'shadowsocks2022'
            ],
            'dns_info' => $dnsInfo,
            'routing_info' => $routingInfo,
            'remarks' => $config['remarks'] ?? 'ShadowSocks2022 Configuration'
        ];
    }

    /**
     * Extract DNS information from V2Ray config
     */
    private function extractDNSInfo(array $config): ?array
    {
        if (!isset($config['dns'])) {
            return null;
        }

        $dns = $config['dns'];
        return [
            'servers' => $dns['servers'] ?? [],
            'hosts' => $dns['hosts'] ?? [],
            'has_advanced_dns' => !empty($dns['servers']) || !empty($dns['hosts'])
        ];
    }

    /**
     * Extract routing information from V2Ray config
     */
    private function extractRoutingInfo(array $config): ?array
    {
        if (!isset($config['routing'])) {
            return null;
        }

        $routing = $config['routing'];
        return [
            'domain_strategy' => $routing['domainStrategy'] ?? 'AsIs',
            'rules_count' => count($routing['rules'] ?? []),
            'has_geo_rules' => $this->hasGeoRules($routing['rules'] ?? []),
            'has_ad_blocking' => $this->hasAdBlocking($routing['rules'] ?? [])
        ];
    }

    /**
     * Check if routing has geo-based rules
     */
    private function hasGeoRules(array $rules): bool
    {
        foreach ($rules as $rule) {
            if (isset($rule['domain']) && is_array($rule['domain'])) {
                foreach ($rule['domain'] as $domain) {
                    if (str_contains($domain, 'geosite:') || str_contains($domain, 'geoip:')) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Check if routing has ad blocking
     */
    private function hasAdBlocking(array $rules): bool
    {
        foreach ($rules as $rule) {
            if (isset($rule['domain']) && is_array($rule['domain'])) {
                foreach ($rule['domain'] as $domain) {
                    if (str_contains($domain, 'category-ads')) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Create default configuration if parsing fails
     */
    private function createDefaultConfig(array $config): array
    {
        return [
            'id' => uniqid('template_'),
            'protocol' => 'unknown',
            'port' => 10808,
            'settings' => [],
            'streamSettings' => [],
            'tag' => 'unknown',
            'listen' => '127.0.0.1',
            'server_info' => null,
            'remarks' => $config['remarks'] ?? 'Unknown Configuration'
        ];
    }

    /**
     * Extract inbound information from V2Ray configuration
     */
    private function extractInboundFromV2Ray(array $inbound, array $fullConfig): array
    {
        $protocol = strtolower($inbound['protocol'] ?? 'socks');
        $port = (int) ($inbound['port'] ?? 10808);
        $listen = $inbound['listen'] ?? '127.0.0.1';
        $tag = $inbound['tag'] ?? 'inbound';

        // Extract settings based on protocol
        $settings = $inbound['settings'] ?? [];
        $streamSettings = $inbound['streamSettings'] ?? [];

        // Handle different protocols
        switch ($protocol) {
            case 'socks':
                $protocol = 'socks5'; // Normalize protocol name
                break;
            case 'http':
                $protocol = 'http';
                break;
            case 'shadowsocks':
                $protocol = 'ss';
                break;
            case 'vmess':
            case 'vless':
            case 'trojan':
                // These are already in correct format
                break;
            default:
                $protocol = 'socks5'; // Default fallback
        }

        // Extract server information from outbounds if available
        $serverInfo = $this->extractServerInfoFromOutbounds($fullConfig);

        $config = [
            'id' => uniqid('template_'),
            'protocol' => $protocol,
            'port' => $port,
            'settings' => $settings,
            'streamSettings' => $streamSettings,
            'tag' => $tag,
            'listen' => $listen,
            'server_info' => $serverInfo
        ];

        return $config;
    }

    /**
     * Extract server information from V2Ray outbounds
     */
    private function extractServerInfoFromOutbounds(array $config): ?array
    {
        if (!isset($config['outbounds']) || !is_array($config['outbounds'])) {
            return null;
        }

        foreach ($config['outbounds'] as $outbound) {
            if (isset($outbound['protocol']) && in_array($outbound['protocol'], ['vmess', 'vless', 'trojan', 'shadowsocks', 'socks'])) {
                if (isset($outbound['settings']['servers']) && is_array($outbound['settings']['servers'])) {
                    $server = $outbound['settings']['servers'][0] ?? null;
                    if ($server && isset($server['address']) && isset($server['port'])) {
                        return [
                            'address' => $server['address'],
                            'port' => $server['port'],
                            'protocol' => $outbound['protocol']
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Parse Hysteria2 configuration
     */
    private function parseHysteria2Config(array $config): array
    {
        $server = $config['server'] ?? '';
        $auth = $config['auth'] ?? '';
        $tls = $config['tls'] ?? [];
        $obfs = $config['obfs'] ?? [];

        // Extract host and port from server
        $serverParts = explode(':', $server);
        $host = $serverParts[0] ?? '';
        $port = (int) ($serverParts[1] ?? 443);

        // Extract SNI from TLS
        $sni = $tls['sni'] ?? '';

        // Extract obfuscation settings
        $obfsType = $obfs['type'] ?? '';
        $obfsPassword = $obfs['salamander']['password'] ?? '';

        return [
            'id' => uniqid('template_'),
            'protocol' => 'hysteria2',
            'port' => $port,
            'settings' => [
                'auth' => $auth,
                'obfs' => [
                    'type' => $obfsType,
                    'password' => $obfsPassword
                ]
            ],
            'streamSettings' => [
                'security' => 'tls',
                'tlsSettings' => [
                    'serverName' => $sni,
                    'insecure' => $tls['insecure'] ?? false
                ]
            ],
            'tag' => 'hysteria2',
            'server_info' => [
                'address' => $host,
                'port' => $port,
                'protocol' => 'hysteria2'
            ]
        ];
    }

    /**
     * Parse URL format configurations
     */
    private function parseUrlFormat(string $url): ?array
    {
        try {
            if (preg_match('/^(vless|vmess|trojan):\/\/([^@]+)@([^:]+):(\d+)(\?.*)?#(.+)$/i', $url, $matches)) {
                $protocol = strtolower($matches[1]);
                $uuid = $matches[2];
                $host = $matches[3];
                $port = (int) $matches[4];
                $query = $matches[5] ?? '';
                $remark = urldecode($matches[6]);

                $queryParams = [];
                if ($query) {
                    parse_str(ltrim($query, '?'), $queryParams);
                }

                $config = [
                    'id' => uniqid('template_'),
                    'protocol' => $protocol,
                    'port' => $port,
                    'settings' => [
                        'clients' => [[
                            'id' => $uuid,
                            'email' => $remark
                        ]]
                    ],
                    'streamSettings' => [
                        'network' => $queryParams['type'] ?? 'tcp',
                        'security' => $queryParams['security'] ?? null
                    ]
                ];

                // Handle WebSocket settings
                if (($queryParams['type'] ?? '') === 'ws') {
                    $config['streamSettings']['wsSettings'] = [
                        'path' => $queryParams['path'] ?? '/',
                        'headers' => [
                            'Host' => $queryParams['host'] ?? $host
                        ]
                    ];
                }

                // Handle gRPC settings
                if (($queryParams['type'] ?? '') === 'grpc') {
                    $config['streamSettings']['grpcSettings'] = [
                        'serviceName' => $queryParams['serviceName'] ?? ''
                    ];
                }

                return $config;
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Parse URL format error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Parse VMESS URL (base64 encoded)
     */
    private function parseVmessUrl(string $url): ?array
    {
        try {
            $encoded = str_replace('vmess://', '', $url);
            $decoded = base64_decode($encoded);
            if ($decoded === false) {
                return null;
            }

            $config = json_decode($decoded, true);
            if (!$config || json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }

            return $this->normalizeConfig($config);

        } catch (\Exception $e) {
            Log::error('Parse VMESS URL error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Normalize configuration structure
     */
    private function normalizeConfig(array $config): array
    {
        $normalized = [
            'id' => $config['id'] ?? uniqid('template_'),
            'protocol' => strtolower($config['protocol'] ?? 'vless'),
            'port' => (int) ($config['port'] ?? 443),
            'settings' => $config['settings'] ?? [],
            'streamSettings' => $config['streamSettings'] ?? $config['stream_settings'] ?? []
        ];

        // Ensure required fields exist
        if (empty($normalized['settings']['clients'])) {
            $normalized['settings']['clients'] = [];
        }

        return $normalized;
    }

    /**
     * Determine the type of inbound configuration
     */
    private function determineConfigType(array $config): string
    {
        if (isset($config['protocol'])) {
            $protocol = strtolower($config['protocol']);
            
            if (in_array($protocol, ['vmess', 'vless', 'trojan', 'shadowsocks', 'socks'])) {
                return 'v2ray';
            }
            if ($protocol === 'hysteria2') {
                return 'hysteria2';
            }
            if ($protocol === 'shadowsocks2022') {
                return 'shadowsocks2022';
            }
            if (in_array($protocol, ['ws', 'grpc'])) {
                return 'url';
            }
        }
        
        return 'custom';
    }

    /**
     * Get all templates for a panel
     */
    public function getTemplatesForPanel($panelId)
    {
        try {
            $templates = InboundTemplate::forPanel($panelId)
                ->active()
                ->with('creator')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $templates
            ]);

        } catch (\Exception $e) {
            Log::error('Get templates error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Server error occurred'
            ], 500);
        }
    }

    /**
     * Get template by ID
     */
    public function getTemplate($id)
    {
        try {
            $template = InboundTemplate::with(['panel', 'creator'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $template
            ]);

        } catch (\Exception $e) {
            Log::error('Get template error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Template not found'
            ], 404);
        }
    }

    /**
     * Update template
     */
    public function updateTemplate(Request $request, $id)
    {
        try {
            $template = InboundTemplate::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'is_active' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $template->update($request->only(['name', 'description', 'is_active']));

            Log::info("Template updated", ['template_id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Template updated successfully',
                'data' => $template
            ]);

        } catch (\Exception $e) {
            Log::error('Update template error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Server error occurred'
            ], 500);
        }
    }

    /**
     * Delete template
     */
    public function deleteTemplate($id)
    {
        try {
            $template = InboundTemplate::findOrFail($id);
            $template->delete();

            Log::info("Template deleted", ['template_id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Template deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Delete template error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Server error occurred'
            ], 500);
        }
    }

    /**
     * Test template configuration
     */
    public function testTemplate($id)
    {
        try {
            $template = InboundTemplate::findOrFail($id);
            
            if (!$template->isValid()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template configuration is invalid'
                ], 400);
            }

            // Here you could add actual connection testing logic
            $testResult = [
                'template_id' => $template->id,
                'protocol' => $template->protocol,
                'port' => $template->port,
                'is_valid' => $template->isValid(),
                'configuration' => $template->toInboundConfig()
            ];

            return response()->json([
                'success' => true,
                'message' => 'Template test completed',
                'data' => $testResult
            ]);

        } catch (\Exception $e) {
            Log::error('Test template error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Server error occurred'
            ], 500);
        }
    }

    /**
     * Test specific configuration parsing
     */
    public function testSpecificConfig(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'config_json' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $config = json_decode($request->config_json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid JSON format',
                    'error' => json_last_error_msg()
                ], 400);
            }

            // Parse the configuration
            $parsedConfig = $this->parseV2RayConfig($config);
            
            if (!$parsedConfig) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not parse configuration'
                ], 400);
            }

            // Determine config type
            $configType = $this->determineConfigType($parsedConfig);

            $result = [
                'success' => true,
                'message' => 'Configuration parsed successfully',
                'data' => [
                    'parsed_config' => $parsedConfig,
                    'config_type' => $configType,
                    'protocol' => $parsedConfig['protocol'] ?? 'unknown',
                    'port' => $parsedConfig['port'] ?? 'unknown',
                    'has_server_info' => !empty($parsedConfig['server_info']),
                    'has_stream_settings' => !empty($parsedConfig['streamSettings']),
                    'extracted_fields' => array_keys($parsedConfig)
                ]
            ];

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Test specific config error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Server error occurred: ' . $e->getMessage()
            ], 500);
        }
    }
}
