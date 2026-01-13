package fr.uge.univ_eiffel.security;

import fr.uge.univ_eiffel.mediators.Brick;
import fr.uge.univ_eiffel.mediators.security.OfflineVerifier;
import org.junit.jupiter.api.Test;
import static org.junit.jupiter.api.Assertions.*;

class OfflineVerifierTest {

    @Test
    void verify_ShouldFail_WithGarbageKey() {
        // ARRANGE
        // 1. Create a Fake Brick
        Brick fakeBrick = new Brick("1-1/red", "0000", "deadbeef");

        // 2. Create a Verifier with a "Valid-looking" but fake public key (Base64)
        // This is random bytes encoded in Base64
        String fakeKey = "MCowBQYDK2VwAyEAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=";
        OfflineVerifier verifier = new OfflineVerifier(fakeKey);

        // ACT
        boolean result = verifier.verify(fakeBrick);

        // ASSERT
        assertFalse(result, "Verification MUST fail if the key does not match the signature");
    }

    @Test
    void verify_ShouldHandle_BrokenKeyFormat() {
        // ARRANGE
        Brick fakeBrick = new Brick("1-1/red", "0000", "deadbeef");

        // Key is not even Base64
        OfflineVerifier verifier = new OfflineVerifier("NOT_A_KEY_AT_ALL");

        // ACT
        boolean result = verifier.verify(fakeBrick);

        // ASSERT
        assertFalse(result, "Should return false gracefully (no crash) on bad key format");
    }

    @Test
    void verify_ShouldFail_IfDataTampered() {
        // ARRANGE
        // Even if the signature format is correct, if it doesn't match the Serial Number, it must fail.
        Brick tamperedBrick = new Brick("2-4/blue", "123456", "abcdef123456");

        // A valid formatted key
        String validFormatKey = "MCowBQYDK2VwAyEAqm4+8sX8d7s7d7s7d7s7d7s7d7s7d7s7d7s7d7s=";
        OfflineVerifier verifier = new OfflineVerifier(validFormatKey);

        // ACT
        boolean result = verifier.verify(tamperedBrick);

        // ASSERT
        assertFalse(result, "Should fail if the data (serial) doesn't match the signature");
    }
}