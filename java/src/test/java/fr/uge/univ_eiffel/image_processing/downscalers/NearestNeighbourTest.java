package fr.uge.univ_eiffel.image_processing.downscalers;

import org.junit.jupiter.api.Test;
import java.awt.image.BufferedImage;
import static org.junit.jupiter.api.Assertions.*;

class NearestNeighbourTest {

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

        NearestNeighbour algo = new NearestNeighbour();

        // Target: 1x1 Pixel
        BufferedImage dest = new BufferedImage(1, 1, BufferedImage.TYPE_INT_ARGB);

        // ACT
        algo.downscale(source, dest);

        // ASSERT
        // Logic: (0 / ratio) -> (0 / 2) = 0. Should pick Top-Left (Red).
        assertEquals(0xFFFF0000, dest.getRGB(0, 0), "Should pick the top-left pixel without blending");
    }
}