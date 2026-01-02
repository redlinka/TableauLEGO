package fr.uge.univ_eiffel.image_processing;

import org.junit.jupiter.api.Test;
import static org.junit.jupiter.api.Assertions.*;

class ImageUtilsTest {

    @Test
    void clamp() {
        // 1. Test "Happy Path" (Value is already safe)
        // If max is 255 and value is 100, it should stay 100
        assertEquals(100, ImageUtils.clamp(100, 255), "Value inside range should not change");

        // 2. Test "Too High" (Value > Max)
        // If value is 300, it should be capped at 255
        assertEquals(255, ImageUtils.clamp(300, 255), "Value above max should be clamped to max");

        // 3. Test "Too Low" (Value < 0)
        // If value is negative, it should be capped at 0 (assuming min is always 0)
        assertEquals(0, ImageUtils.clamp(-50, 255), "Negative value should be clamped to 0");
    }

    @Test
    void extractARGB() {
        // We use a known color: Opaque Red
        // Alpha=255 (0xFF), Red=255 (0xFF), Green=0, Blue=0
        // In Hex: 0xFFFF0000
        int redPixel = 0xFFFF0000;

        int[] components = ImageUtils.extractARGB(redPixel);

        assertNotNull(components);
        assertEquals(4, components.length, "Should return exactly 4 channels (ARGB)");
        assertEquals(255, components[0], "Alpha should be 255");
        assertEquals(255, components[1], "Red should be 255");
        assertEquals(0, components[2],   "Green should be 0");
        assertEquals(0, components[3],   "Blue should be 0");
    }

    @Test
    void combineARGB() {
        // This is a "Round Trip" test.
        // If we split a color and put it back together, it should be identical.

        int originalPixel = 0xFF123456; // Random color (A=255, R=18, G=52, B=86)

        // 1. Extract it
        int[] parts = ImageUtils.extractARGB(originalPixel);

        // 2. Re-combine it
        int result = ImageUtils.combineARGB(parts);

        // 3. Compare
        assertEquals(originalPixel, result, "Combining ARGB should restore the original pixel integer");
    }
}