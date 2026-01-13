package fr.uge.univ_eiffel.image_processing;

import org.junit.jupiter.api.Test;
import static org.junit.jupiter.api.Assertions.*;

class ImageUtilsTest {

    // --- 1. Testing Math Logic (Clamp) ---

    @Test
    void clamp_ShouldKeepValue_WhenInsideRange() {
        // If max is 100 and value is 50, it should stay 50.
        int result = ImageUtils.clamp(50, 100);
        assertEquals(50, result, "Value inside valid range should not change");
    }

    @Test
    void clamp_ShouldCapValue_WhenTooHigh() {
        // If value is 150 but max is 100, it must return 100.
        int result = ImageUtils.clamp(150, 100);
        assertEquals(100, result, "Value exceeding max must be clamped to max");
    }

    @Test
    void clamp_ShouldReturnZero_WhenNegative() {
        // Your code uses Math.max(0, ...), so negative inputs become 0.
        int result = ImageUtils.clamp(-10, 100);
        assertEquals(0, result, "Negative value must be clamped to 0");
    }

    // --- 2. Testing Color Logic (ARGB) ---

    @Test
    void extractARGB_ShouldSplitColorCorrectly() {
        // We use a specific color: Pure Red
        // Alpha=255 (0xFF), Red=255 (0xFF), Green=0, Blue=0
        // Hex representation: 0xFFFF0000
        int redPixel = 0xFFFF0000;

        int[] components = ImageUtils.extractARGB(redPixel);

        // Verify the array structure
        assertNotNull(components);
        assertEquals(4, components.length, "Must return 4 components [A, R, G, B]");

        // Verify values
        assertEquals(255, components[0], "Alpha channel incorrect");
        assertEquals(255, components[1], "Red channel incorrect");
        assertEquals(0,   components[2], "Green channel incorrect");
        assertEquals(0,   components[3], "Blue channel incorrect");
    }

    @Test
    void combineARGB_ShouldReverseExtract() {
        // "Round Trip" Test: Extract -> Combine -> Should match original
        int original = 0xFF123456; // Random color

        int[] parts = ImageUtils.extractARGB(original);
        int result = ImageUtils.combineARGB(parts);

        assertEquals(original, result, "Combining the extracted parts should restore the exact original integer");
    }
}