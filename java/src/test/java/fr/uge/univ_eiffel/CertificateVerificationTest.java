package fr.uge.univ_eiffel;

import org.junit.jupiter.api.Test;
import static org.junit.jupiter.api.Assertions.*;

class CertificateVerificationTest {

    @Test
    void verify_ShouldReject_GarbageSignature() {
        // ARRANGE
        // Create a brick with nonsense data
        // Name: "1-1/red", Serial: "0000", Cert: "deadbeef" (Invalid signature)
        Brick fakeBrick = new Brick("1-1/red", "0000", "deadbeef");

        // A valid-looking but random Public Key (Base64 encoded)
        // This is just a random 32-byte key encoded in Base64
        String randomPublicKey = "MCowBQYDK2VwAyEAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=";

        // ACT
        // This should internally throw a SignatureException or return false
        boolean result = CertificateVerification.verify(fakeBrick, randomPublicKey);

        // ASSERT
        assertFalse(result, "Verification MUST fail for an invalid signature");
    }

    @Test
    void verify_ShouldReject_InvalidPublicKeyFormat() {
        // ARRANGE
        Brick fakeBrick = new Brick("1-1/red", "0000", "deadbeef");

        // Completely broken key string (Not Base64)
        String brokenKey = "Not a key!!";

        // ACT
        boolean result = CertificateVerification.verify(fakeBrick, brokenKey);

        // ASSERT
        // The method catches exceptions and returns false.
        // We want to make sure it doesn't crash the program.
        assertFalse(result, "Should return false (gracefully) if public key format is wrong");
    }

    @Test
    void verify_ShouldReject_IfDataTampered() {
        // ARRANGE
        // Even if we had a valid signature for "Serial A",
        // if we change the brick to "Serial B", it must fail.

        // We simulate this by passing mismatched hex strings
        Brick tamperedBrick = new Brick("2-4/blue", "123456", "abcdef123456");

        // Standard Ed25519 Public Key header + random bytes
        String validFormatKey = "MCowBQYDK2VwAyEAqm4+8sX8d7s7d7s7d7s7d7s7d7s7d7s7d7s7d7s=";

        // ACT
        boolean result = CertificateVerification.verify(tamperedBrick, validFormatKey);

        // ASSERT
        assertFalse(result, "Should fail if the data doesn't match the signature");
    }
}