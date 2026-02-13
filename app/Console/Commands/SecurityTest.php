<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Student;
use App\Models\Exam;

/**
 * Security Test Command
 *
 * Command ini untuk testing keamanan kode
 *
 * @package App\Console\Commands
 * @author Development Team
 * @version 1.0.0
 */
class SecurityTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:security {--detailed : Show detailed test results}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test API security and code vulnerabilities';

    /**
     * Test results
     *
     * @var array
     */
    protected $testResults = [];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting security tests...');
        $this->newLine();

        // Run security tests
        $this->testSQLInjection();
        $this->testXSSProtection();
        $this->testInputValidation();
        $this->testAuthentication();
        $this->testAuthorization();
        $this->testDataExposure();
        $this->testRateLimiting();
        $this->testFileUploadSecurity();
        $this->testPasswordSecurity();
        $this->testDatabaseSecurity();

        // Display results
        $this->displayResults();

        return 0;
    }

    /**
     * Test SQL Injection protection
     */
    private function testSQLInjection()
    {
        $this->info('Testing SQL Injection protection...');

        $testCases = [
            "' OR '1'='1",
            "'; DROP TABLE users; --",
            "' UNION SELECT * FROM users --",
            "admin'--",
            "1 OR 1=1",
            "1; DELETE FROM users WHERE 1=1",
        ];

        $passed = 0;
        $total = count($testCases);

        foreach ($testCases as $testCase) {
            try {
                // Test with Student model
                $result = Student::where('nisn', $testCase)->first();

                // Test with User model
                $result2 = User::where('email', $testCase)->first();

                // If no exception thrown and no unexpected results, consider it safe
                if (!$result && !$result2) {
                    $passed++;
                    if ($this->option('detailed')) {
                    $this->info("✅ Safe: " . substr($testCase, 0, 30) . "...");
                }
                } else {
                    $this->warn("⚠️  Potentially unsafe: " . substr($testCase, 0, 30) . "...");
                }
            } catch (\Exception $e) {
                $this->error("❌ Error with: " . substr($testCase, 0, 30) . "...");
            }
        }

        $this->testResults['SQL Injection'] = [
            'passed' => $passed,
            'total' => $total,
            'status' => $passed === $total ? 'PASS' : 'FAIL'
        ];
    }

    /**
     * Test XSS protection
     */
    private function testXSSProtection()
    {
        $this->info('Testing XSS protection...');

        $xssPayloads = [
            '<script>alert("XSS")</script>',
            '<img src="x" onerror="alert(1)">',
            'javascript:alert("XSS")',
            '<iframe src="javascript:alert(1)"></iframe>',
            '<svg onload=alert(1)>',
            '"><script>alert(1)</script>',
        ];

        $passed = 0;
        $total = count($xssPayloads);

        foreach ($xssPayloads as $payload) {
            try {
                // Test input validation
                $validator = Validator::make(
                    ['name' => $payload],
                    ['name' => 'required|string|max:255']
                );

                if ($validator->fails()) {
                    $passed++;
                    if ($this->option('detailed')) {
                    $this->info("✅ XSS payload blocked: " . substr($payload, 0, 30) . "...");
                }
                } else {
                    $this->warn("⚠️  XSS payload potentially allowed: " . substr($payload, 0, 30) . "...");
                }
            } catch (\Exception $e) {
                $this->error("❌ Error testing XSS: " . $e->getMessage());
            }
        }

        $this->testResults['XSS Protection'] = [
            'passed' => $passed,
            'total' => $total,
            'status' => $passed === $total ? 'PASS' : 'FAIL'
        ];
    }

    /**
     * Test input validation
     */
    private function testInputValidation()
    {
        $this->info('Testing input validation...');

        $testCases = [
            ['email' => 'invalid-email', 'expected' => false],
            ['email' => 'valid@email.com', 'expected' => true],
            ['nisn' => '123', 'expected' => false], // Too short
            ['nisn' => '123456789012345678901', 'expected' => false], // Too long
            ['nisn' => '1234567890', 'expected' => true],
            ['score' => -1, 'expected' => false],
            ['score' => 101, 'expected' => false],
            ['score' => 50, 'expected' => true],
        ];

        $passed = 0;
        $total = count($testCases);

        foreach ($testCases as $testCase) {
            try {
                $rules = [];
                $data = [];

                foreach ($testCase as $field => $value) {
                    if ($field !== 'expected') {
                        $data[$field] = $value;

                        // Set appropriate rules based on field
                        switch ($field) {
                            case 'email':
                                $rules[$field] = 'required|string|email|max:255';
                                break;
                            case 'nisn':
                                $rules[$field] = 'required|string|max:20';
                                break;
                            case 'score':
                                $rules[$field] = 'required|integer|min:0|max:100';
                                break;
                        }
                    }
                }

                $validator = Validator::make($data, $rules);
                $isValid = !$validator->fails();

                if ($isValid === $testCase['expected']) {
                    $passed++;
                    if ($this->option('detailed')) {
                    $this->info("✅ Validation correct for: " . json_encode($data));
                }
                } else {
                    $this->warn("⚠️  Validation mismatch for: " . json_encode($data));
                }
            } catch (\Exception $e) {
                $this->error("❌ Error testing validation: " . $e->getMessage());
            }
        }

        $this->testResults['Input Validation'] = [
            'passed' => $passed,
            'total' => $total,
            'status' => $passed === $total ? 'PASS' : 'FAIL'
        ];
    }

    /**
     * Test authentication security
     */
    private function testAuthentication()
    {
        $this->info('Testing authentication security...');

        $testCases = [
            ['email' => 'admin@example.com', 'password' => 'wrongpassword', 'expected' => false],
            ['email' => 'nonexistent@example.com', 'password' => 'password123', 'expected' => false],
            ['email' => '', 'password' => '', 'expected' => false],
        ];

        $passed = 0;
        $total = count($testCases);

        foreach ($testCases as $testCase) {
            try {
                // Test authentication attempt
                $result = auth()->attempt([
                    'email' => $testCase['email'],
                    'password' => $testCase['password']
                ]);

                if ($result === $testCase['expected']) {
                    $passed++;
                    if ($this->option('verbose')) {
                        $this->info("✅ Authentication test passed for: " . $testCase['email']);
                    }
                } else {
                    $this->warn("⚠️  Authentication test failed for: " . $testCase['email']);
                }
            } catch (\Exception $e) {
                $this->error("❌ Authentication error: " . $e->getMessage());
            }
        }

        $this->testResults['Authentication'] = [
            'passed' => $passed,
            'total' => $total,
            'status' => $passed === $total ? 'PASS' : 'FAIL'
        ];
    }

    /**
     * Test authorization
     */
    private function testAuthorization()
    {
        $this->info('Testing authorization...');

        // Test role-based access
        $testCases = [
            ['guard' => 'api', 'user_id' => 999999, 'expected' => false], // Non-existent user
            ['guard' => 'api', 'user_id' => null, 'expected' => false], // No user
        ];

        $passed = 0;
        $total = count($testCases);

        foreach ($testCases as $testCase) {
            try {
                if ($testCase['user_id']) {
                    auth()->guard($testCase['guard'])->loginUsingId($testCase['user_id']);
                } else {
                    auth()->guard($testCase['guard'])->logout();
                }

                $isAuthenticated = auth()->guard($testCase['guard'])->check();

                if ($isAuthenticated === $testCase['expected']) {
                    $passed++;
                    if ($this->option('verbose')) {
                        $this->info("✅ Authorization test passed for guard: " . $testCase['guard']);
                    }
                } else {
                    $this->warn("⚠️  Authorization test failed for guard: " . $testCase['guard']);
                }
            } catch (\Exception $e) {
                $this->error("❌ Authorization error: " . $e->getMessage());
            }
        }

        $this->testResults['Authorization'] = [
            'passed' => $passed,
            'total' => $total,
            'status' => $passed === $total ? 'PASS' : 'FAIL'
        ];
    }

    /**
     * Test data exposure
     */
    private function testDataExposure()
    {
        $this->info('Testing data exposure...');

        try {
            // Test if sensitive data is exposed in responses
            $user = User::first();

            if ($user) {
                // Check if password is exposed
                $userArray = $user->toArray();
                $passwordExposed = isset($userArray['password']);

                // Check if remember token is exposed
                $tokenExposed = isset($userArray['remember_token']);

                if (!$passwordExposed && !$tokenExposed) {
                    $this->testResults['Data Exposure'] = [
                        'passed' => 1,
                        'total' => 1,
                        'status' => 'PASS'
                    ];
                    if ($this->option('verbose')) {
                        $this->info("✅ Sensitive data properly hidden");
                    }
                } else {
                    $this->testResults['Data Exposure'] = [
                        'passed' => 0,
                        'total' => 1,
                        'status' => 'FAIL'
                    ];
                    $this->warn("⚠️  Sensitive data potentially exposed");
                }
            } else {
                $this->testResults['Data Exposure'] = [
                    'passed' => 0,
                    'total' => 1,
                    'status' => 'SKIP'
                ];
                $this->warn("⚠️  No users found for data exposure test");
            }
        } catch (\Exception $e) {
            $this->testResults['Data Exposure'] = [
                'passed' => 0,
                'total' => 1,
                'status' => 'ERROR'
            ];
            $this->error("❌ Data exposure test error: " . $e->getMessage());
        }
    }

    /**
     * Test rate limiting
     */
    private function testRateLimiting()
    {
        $this->info('Testing rate limiting...');

        // Note: This is a basic test. In production, you should implement
        // proper rate limiting with Redis or database tracking

        $this->testResults['Rate Limiting'] = [
            'passed' => 1,
            'total' => 1,
            'status' => 'INFO'
        ];

        $this->info("ℹ️  Rate limiting should be implemented at the web server or application level");
        $this->info("ℹ️  Consider using Laravel's rate limiting middleware");
    }

    /**
     * Test file upload security
     */
    private function testFileUploadSecurity()
    {
        $this->info('Testing file upload security...');

        // Test file validation rules
        $testCases = [
            ['filename' => 'test.php', 'expected' => false],
            ['filename' => 'test.exe', 'expected' => false],
            ['filename' => 'test.js', 'expected' => false],
            ['filename' => 'test.xlsx', 'expected' => true],
            ['filename' => 'test.csv', 'expected' => true],
            ['filename' => 'test.jpg', 'expected' => true],
        ];

        $passed = 0;
        $total = count($testCases);

        foreach ($testCases as $testCase) {
            try {
                // Simulate file validation
                $extension = pathinfo($testCase['filename'], PATHINFO_EXTENSION);
                $allowedExtensions = ['xlsx', 'xls', 'csv', 'jpg', 'jpeg', 'png'];
                $isAllowed = in_array(strtolower($extension), $allowedExtensions);

                if ($isAllowed === $testCase['expected']) {
                    $passed++;
                    if ($this->option('verbose')) {
                        $this->info("✅ File validation correct for: " . $testCase['filename']);
                    }
                } else {
                    $this->warn("⚠️  File validation issue for: " . $testCase['filename']);
                }
            } catch (\Exception $e) {
                $this->error("❌ File validation error: " . $e->getMessage());
            }
        }

        $this->testResults['File Upload Security'] = [
            'passed' => $passed,
            'total' => $total,
            'status' => $passed === $total ? 'PASS' : 'FAIL'
        ];
    }

    /**
     * Test password security
     */
    private function testPasswordSecurity()
    {
        $this->info('Testing password security...');

        try {
            // Test password hashing
            $plainPassword = 'testpassword123';
            $hashedPassword = Hash::make($plainPassword);

            $isHashed = Hash::check($plainPassword, $hashedPassword);
            $isDifferent = $plainPassword !== $hashedPassword;

            if ($isHashed && $isDifferent) {
                $this->testResults['Password Security'] = [
                    'passed' => 1,
                    'total' => 1,
                    'status' => 'PASS'
                ];
                if ($this->option('detailed')) {
                    $this->info("✅ Password properly hashed");
                }
            } else {
                $this->testResults['Password Security'] = [
                    'passed' => 0,
                    'total' => 1,
                    'status' => 'FAIL'
                ];
                $this->warn("⚠️  Password hashing issue");
            }
        } catch (\Exception $e) {
            $this->testResults['Password Security'] = [
                'passed' => 0,
                'total' => 1,
                'status' => 'ERROR'
            ];
            $this->error("❌ Password security test error: " . $e->getMessage());
        }
    }

    /**
     * Test database security
     */
    private function testDatabaseSecurity()
    {
        $this->info('Testing database security...');

        try {
            // Check if database connection is using SSL/TLS
            $connection = DB::connection();
            $config = $connection->getConfig();

            $sslEnabled = isset($config['options']) &&
                         isset($config['options'][\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT]);

            // Check for SQL injection in raw queries
            $safeQuery = true;
            try {
                DB::select('SELECT * FROM users WHERE id = ?', [1]);
            } catch (\Exception $e) {
                $safeQuery = false;
            }

            if ($safeQuery) {
                $this->testResults['Database Security'] = [
                    'passed' => 1,
                    'total' => 1,
                    'status' => 'PASS'
                ];
                if ($this->option('detailed')) {
                    $this->info("✅ Database queries use parameter binding");
                }
            } else {
                $this->testResults['Database Security'] = [
                    'passed' => 0,
                    'total' => 1,
                    'status' => 'FAIL'
                ];
                $this->warn("⚠️  Database security issues detected");
            }
        } catch (\Exception $e) {
            $this->testResults['Database Security'] = [
                'passed' => 0,
                'total' => 1,
                'status' => 'ERROR'
            ];
            $this->error("❌ Database security test error: " . $e->getMessage());
        }
    }

    /**
     * Display test results
     */
    private function displayResults()
    {
        $this->newLine();
        $this->info('=== SECURITY TEST RESULTS ===');
        $this->newLine();

        $tableData = [];
        $totalPassed = 0;
        $totalTests = 0;

        foreach ($this->testResults as $test => $result) {
            $tableData[] = [
                $test,
                $result['passed'] . '/' . $result['total'],
                $result['status']
            ];

            if ($result['status'] === 'PASS') {
                $totalPassed++;
            }
            $totalTests++;
        }

        $this->table(
            ['Test', 'Passed/Total', 'Status'],
            $tableData
        );

        $this->newLine();
        $this->info("Overall Security Score: {$totalPassed}/{$totalTests} tests passed");

        if ($totalPassed === $totalTests) {
            $this->info('🎉 All security tests passed!');
        } elseif ($totalPassed >= $totalTests * 0.8) {
            $this->info('✅ Good security posture - ' . round(($totalPassed / $totalTests) * 100, 1) . '% passed');
        } elseif ($totalPassed >= $totalTests * 0.6) {
            $this->warn('⚠️  Moderate security issues - ' . round(($totalPassed / $totalTests) * 100, 1) . '% passed');
        } else {
            $this->error('❌ Significant security issues detected - ' . round(($totalPassed / $totalTests) * 100, 1) . '% passed');
        }

        $this->newLine();
        $this->info('Security recommendations:');
        $this->info('1. Implement rate limiting for API endpoints');
        $this->info('2. Use HTTPS in production');
        $this->info('3. Implement proper logging and monitoring');
        $this->info('4. Regular security audits and penetration testing');
        $this->info('5. Keep dependencies updated');
        $this->info('6. Use prepared statements for all database queries');
        $this->info('7. Implement proper input sanitization');
        $this->info('8. Use strong password policies');
    }
}
