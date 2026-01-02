package fr.uge.univ_eiffel.image_processing.downscalers;

import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import java.awt.image.BufferedImage;
import static org.junit.jupiter.api.Assertions.*;

class NearestNeighbourTest {

    private NearestNeighbour interpolator;

    @BeforeEach
    void setUp() {
        interpolator = new NearestNeighbour();
    }

    @Test
    void downscale_ShouldPickExactPixel() {
        // ARRANGE: 2x2 Image with distinct colors
        // [Red   Green]
        // [Blue  Yellow]
        BufferedImage source = new BufferedImage(2, 2, BufferedImage.TYPE_INT_ARGB);
        source.setRGB(0, 0, 0xFFFF0000); // Red
        source.setRGB(1, 0, 0xFF00FF00); // Green
        source.setRGB(0, 1, 0xFF0000FF); // Blue
        source.setRGB(1, 1, 0xFFFFFF00); // Yellow

        // Shrink to 1x1
        BufferedImage dest = new BufferedImage(1, 1, BufferedImage.TYPE_INT_ARGB);

        // ACT
        interpolator.downscale(source, dest);

        // ASSERT
        // Nearest Neighbour logic usually picks the top-left (0,0) or center.
        // Based on your code: (int)(x / ratio) -> (0/2) = 0.
        // So it should pick Red (0,0).
        assertEquals(0xFFFF0000, dest.getRGB(0, 0), "Should strictly pick the nearest pixel (Top-Left)");
    }
}