package fr.uge.univ_eiffel.security;

import fr.uge.univ_eiffel.mediators.Brick;
import fr.uge.univ_eiffel.mediators.security.OnlineVerifier;
import org.junit.jupiter.api.Test;
import static org.junit.jupiter.api.Assertions.*;

class OnlineVerifierTest {

    @Test
    void verify_ShouldReturnFalse_WhenClientFails() {
        // ARRANGE
        // We pass 'null' as the client.
        // In your code, this will cause a NullPointerException when calling client.verify()
        // BUT, your code wraps it in 'try { ... } catch (Exception e)'.
        // So it should catch the crash and return false.
        OnlineVerifier verifier = new OnlineVerifier(null);

        Brick fakeBrick = new Brick("1-1/red", "00", "00");

        // ACT
        boolean result = verifier.verify(fakeBrick);

        // ASSERT
        assertFalse(result, "Verifier should return false (not crash) if the client connection fails");
    }
}