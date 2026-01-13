package fr.uge.univ_eiffel;

import org.junit.jupiter.api.Test;
import static org.junit.jupiter.api.Assertions.*;

class CliInputTest {

    // --- ManualRestock Tests ---

    @Test
    void manualRestock_ShouldExit_WhenNoArgsProvided() {
        String[] args = {};
        // Should print usage and exit (or return) without crashing
        assertDoesNotThrow(() -> ManualRestock.main(args));
    }

    // --- ReactionRestock Tests ---

    @Test
    void reactionRestock_ShouldExit_WhenMissingArgs() {
        String[] args = {"output.txt"};
        assertDoesNotThrow(() -> ReactionRestock.main(args));
    }

    @Test
    void reactionRestock_ShouldExit_WhenImageIdIsNotNumber() {
        String[] args = {"output.txt", "abc"};
        assertDoesNotThrow(() -> ReactionRestock.main(args));
    }
}