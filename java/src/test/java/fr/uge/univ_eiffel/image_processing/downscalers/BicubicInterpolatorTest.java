package fr.uge.univ_eiffel.image_processing.downscalers;

import org.junit.jupiter.api.Test;
import java.awt.image.BufferedImage;
import static org.junit.jupiter.api.Assertions.*;

class BicubicInterpolatorTest {

    @Test
    void downscale_ShouldRunWithoutCrashing() {
        // Bicubic math checks neighbors at -1 and +2.
        // We verify it clamps edges correctly instead of crashing.

        BufferedImage source = new BufferedImage(4, 4, BufferedImage.TYPE_INT_ARGB);
        BufferedImage dest = new BufferedImage(2, 2, BufferedImage.TYPE_INT_ARGB);

        BicubicInterpolator algo = new BicubicInterpolator();

        assertDoesNotThrow(() -> algo.downscale(source, dest), "Bicubic should handle edge pixels safely");
    }

    @Test
    void downscale_PreservesSolidColor() {
        // If the whole image is RED, the result must be RED (no math artifacts)
        BufferedImage source = new BufferedImage(10, 10, BufferedImage.TYPE_INT_ARGB);
        for(int x=0; x<10; x++)
            for(int y=0; y<10; y++)
                source.setRGB(x, y, 0xFFFF0000); // Red

        BufferedImage dest = new BufferedImage(5, 5, BufferedImage.TYPE_INT_ARGB);

        new BicubicInterpolator().downscale(source, dest);

        assertEquals(0xFFFF0000, dest.getRGB(0, 0), "Solid color should remain unchanged after interpolation");
    }
}