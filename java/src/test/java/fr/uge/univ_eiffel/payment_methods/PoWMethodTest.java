package fr.uge.univ_eiffel.payment_methods;

import fr.uge.univ_eiffel.mediators.payment_methods.PoWMethod;
import org.junit.jupiter.api.Test;
import static org.junit.jupiter.api.Assertions.*;

class PoWMethodTest {

    @Test
    void solveChallenge_ShouldFormatAnswerCorrectly() {
        // ARRANGE
        // We pass 'null' for the client because solveChallenge DOES NOT use the client.
        // It only uses the static POW_SOLVER.
        PoWMethod method = new PoWMethod(null);

        // "01" is data prefix (byte 1)
        // "AA" is hash prefix (byte 0xAA - usually easy to find quickly)
        PoWMethod.Challenge fakeChallenge = new PoWMethod.Challenge("01", "AA");

        // ACT
        // This runs the real solver!
        PoWMethod.ChallengeAnswer answer = method.solveChallenge(fakeChallenge);

        // ASSERT
        assertNotNull(answer);

        // 1. Check integrity
        assertEquals("01", answer.data_prefix());
        assertEquals("AA", answer.hash_prefix());

        // 2. Check Answer Format (Must be a valid Hex string)
        String hexAnswer = answer.answer();
        assertNotNull(hexAnswer);
        assertTrue(hexAnswer.length() > 2, "Answer should be longer than the prefix");
        assertTrue(hexAnswer.matches("^[0-9a-fA-F]+$"), "Answer must be a valid Hex string");
    }
}