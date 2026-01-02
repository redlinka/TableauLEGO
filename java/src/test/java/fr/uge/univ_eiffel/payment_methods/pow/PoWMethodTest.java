package fr.uge.univ_eiffel.payment_methods.pow;

import fr.uge.univ_eiffel.mediators.FactoryClient;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import java.io.IOException;

import static org.junit.jupiter.api.Assertions.*;

class PoWMethodTest {

    private PoWMethod method;

    @BeforeEach
    void setUp() throws IOException {
        // We can pass null for the client because we are only testing methods
        // that DO NOT use the client (like solveChallenge).
        // If we tested 'pay()', this would crash.
        method = new PoWMethod(null);
    }

    @Test
    void solveChallenge_ShouldFormatAnswerCorrectly() {
        // ARRANGE
        // Create a fake challenge: Prefix "0102" (bytes 1,2), Target Hash "AB"
        PoWMethod.Challenge fakeChallenge = new PoWMethod.Challenge("0102", "AB");

        // ACT
        PoWMethod.ChallengeAnswer answer = method.solveChallenge(fakeChallenge);

        // ASSERT
        assertNotNull(answer);
        assertEquals("0102", answer.data_prefix());
        assertEquals("AB", answer.hash_prefix());

        // The answer should be a hex string (longer than the prefix)
        assertNotNull(answer.answer());
        assertTrue(answer.answer().length() > 4, "Answer should be a hex string containing the solution");

        // Verify it didn't crash on Hex parsing
        assertTrue(answer.answer().matches("^[0-9a-fA-F]+$"), "Answer must be valid Hex");
    }
}