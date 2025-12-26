package fr.uge.univ_eiffel.image_processing.downscalers;

import java.awt.image.BufferedImage;

/** Interface defining the contract for image resizing algorithms.
 * Implementations must handle the logic for reducing image resolution.
 * Contains default helper methods for pixel manipulation. */
public interface Downscaler {

    /** Helper to keep color values within the valid 0-255 range.
     * Input: Value to check and the maximum allowed (usually 255).
     * Output: The clamped integer value. */
    default int clamp(int value, int max) {
        return Math.max(0, Math.min(max, value));
    }

    /** Splits a packed 32-bit ARGB integer into its 4 component bytes.
     * Input: Packed int (0xAARRGGBB).
     * Output: int array {Alpha, Red, Green, Blue}. */
    default int[] extractARGB(int argb) {
        return new int[] {
                (argb >> 24) & 0xFF,  // a
                (argb >> 16) & 0xFF,  // r
                (argb >> 8) & 0xFF,   // g
                argb & 0xFF           // b
        };
    }

    /** Merges 4 component values back into a single 32-bit integer.
     * Input: int array {Alpha, Red, Green, Blue}.
     * Output: Packed ARGB int. */
    default int combineARGB(int[] argb) {
        return (argb[0] << 24) | (argb[1] << 16) | (argb[2] << 8) | argb[3];
    }

    /** Core method to resize an image.
     * Implementations define the specific algorithm (Linear, Cubic, etc.).
     * Input: Source image and the blank destination image (with target dims).
     * Output: void (modifies destination in place). */
    void downscale(BufferedImage source, BufferedImage destination);
}
