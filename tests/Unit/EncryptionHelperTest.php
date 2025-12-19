<?php

namespace Phpfy\LaravelBackup\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Phpfy\LaravelBackup\Helpers\EncryptionHelper;
use RuntimeException;

class EncryptionHelperTest extends TestCase
{
    /** @test */
    public function it_can_encrypt_and_decrypt_data()
    {
        $data = 'This is sensitive backup data';
        $password = 'my-secure-password';

        $encrypted = EncryptionHelper::encrypt($data, $password);
        $decrypted = EncryptionHelper::decrypt($encrypted, $password);

        $this->assertEquals($data, $decrypted);
        $this->assertNotEquals($data, $encrypted);
    }

    /** @test */
    public function it_throws_exception_on_wrong_password()
    {
        $data = 'Secret data';
        $encrypted = EncryptionHelper::encrypt($data, 'correct-password');

        $this->expectException(RuntimeException::class);
        EncryptionHelper::decrypt($encrypted, 'wrong-password');
    }

    /** @test */
    public function it_throws_exception_on_empty_password()
    {
        $this->expectException(RuntimeException::class);
        EncryptionHelper::encrypt('data', '');
    }

    /** @test */
    public function it_can_encrypt_and_decrypt_files()
    {
        $content = 'File content for backup';

        // Create source file
        $sourceFile = tempnam(sys_get_temp_dir(), 'source');
        file_put_contents($sourceFile, $content);

        $encryptedFile = tempnam(sys_get_temp_dir(), 'encrypted');
        $decryptedFile = tempnam(sys_get_temp_dir(), 'decrypted');

        $password = 'file-password';

        // Encrypt
        EncryptionHelper::encryptFile($sourceFile, $encryptedFile, $password);
        $this->assertFileExists($encryptedFile);

        // Decrypt
        EncryptionHelper::decryptFile($encryptedFile, $decryptedFile, $password);
        $this->assertFileExists($decryptedFile);

        // Verify content
        $this->assertEquals($content, file_get_contents($decryptedFile));

        // Cleanup
        unlink($sourceFile);
        unlink($encryptedFile);
        unlink($decryptedFile);
    }

    /** @test */
    public function it_generates_random_passwords()
    {
        $password1 = EncryptionHelper::generatePassword(32);
        $password2 = EncryptionHelper::generatePassword(32);

        $this->assertNotEmpty($password1);
        $this->assertNotEmpty($password2);
        $this->assertNotEquals($password1, $password2);
    }

    /** @test */
    public function it_checks_openssl_availability()
    {
        $this->assertTrue(EncryptionHelper::isAvailable());
    }

    /** @test */
    public function it_validates_password_strength()
    {
        $this->assertFalse(EncryptionHelper::isPasswordStrong('weak'));
        $this->assertFalse(EncryptionHelper::isPasswordStrong('12345678'));
        $this->assertTrue(EncryptionHelper::isPasswordStrong('MyP@ssw0rd!'));
        $this->assertTrue(EncryptionHelper::isPasswordStrong('Str0ng!Pass'));
    }

    /** @test */
    public function it_hashes_and_verifies_passwords()
    {
        $password = 'my-password';
        $hash = EncryptionHelper::hashPassword($password);

        $this->assertTrue(EncryptionHelper::verifyPassword($password, $hash));
        $this->assertFalse(EncryptionHelper::verifyPassword('wrong-password', $hash));
    }
}