package fr.uge.univ_eiffel.image_processing.downscalers;

import java.awt.image.BufferedImage;

/** Interface defining the contract for image resizing algorithms.
 * Implementations must handle the logic for reducing image resolution.
 * Contains default helper methods for pixel manipulation. */
public sealed interface Downscaler permits NearestNeighbour, BilinearInterpolator, BicubicInterpolator{

    /** Core method to resize an image.
     * Implementations define the specific algorithm (Linear, Cubic, etc.).
     * Input: Source image and the blank destination image (with target dims).
     * Output: void (modifies destination in place). */
    void downscale(BufferedImage source, BufferedImage destination);
}
