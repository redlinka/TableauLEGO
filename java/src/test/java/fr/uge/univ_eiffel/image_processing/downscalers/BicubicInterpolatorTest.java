package fr.uge.univ_eiffel.image_processing.downscalers;

import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;

import java.awt.image.BufferedImage;

import static org.junit.jupiter.api.Assertions.*;

class BicubicInterpolatorTest {

    private BicubicInterpolator interpolator;
    private BufferedImage sourceImage;
    private BufferedImage destImage;

    @BeforeEach
    void setUp() {
        interpolator = new BicubicInterpolator();

        // Create a simple 4x4 white image to test shrinking
        sourceImage = new BufferedImage(4, 4, BufferedImage.TYPE_INT_ARGB);

        // Create a smaller 2x2 destination
        destImage = new BufferedImage(2, 2, BufferedImage.TYPE_INT_ARGB);
    }

    @Test
    void downscale_ShouldResizeWithoutCrashing() {
        // Just checking if the math runs without throwing exceptions
        assertDoesNotThrow(() -> {
            interpolator.downscale(sourceImage, destImage);
        });
    }

    @Test
    void downscale_ShouldPopulateDestination() {
        // 1. Fill source with RED
        for(int x=0; x<4; x++) {
            for(int y=0; y<4; y++) {
                sourceImage.setRGB(x, y, 0xFFFF0000); // ARGB Red
            }
        }

        // 2. Run downscale
        interpolator.downscale(sourceImage, destImage);

        // 3. Check a pixel in the destination
        // If we shrink a pure red image, the result should still be red!
        int resultPixel = destImage.getRGB(0, 0);

        assertEquals(0xFFFF0000, resultPixel, "Downscaling a solid color should preserve that color");
    }
}