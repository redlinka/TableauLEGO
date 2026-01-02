package fr.uge.univ_eiffel.image_processing.downscalers;

import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import java.awt.image.BufferedImage;
import static org.junit.jupiter.api.Assertions.*;

class BilinearInterpolatorTest {

    private BilinearInterpolator interpolator;
    private BufferedImage source;
    private BufferedImage dest;

    @BeforeEach
    void setUp() {
        interpolator = new BilinearInterpolator();
    }

    @Test
    void downscale_ShouldBlendColors_OnUpscale() {
        // ARRANGE: Create a 2x1 image [Black | White]
        source = new BufferedImage(2, 1, BufferedImage.TYPE_INT_ARGB);
        source.setRGB(0, 0, 0xFF000000); // Black (0)
        source.setRGB(1, 0, 0xFFFFFFFF); // White (255)

        // DEST: 3x1 image (Upscaling forces interpolation)
        // Pixel 0 maps to 0.0 (Black)
        // Pixel 1 maps to 0.66 (Blend of Black/White)
        // Pixel 2 maps to 1.33 (White)
        dest = new BufferedImage(3, 1, BufferedImage.TYPE_INT_ARGB);

        // ACT
        interpolator.downscale(source, dest);

        // ASSERT
        // Check the MIDDLE pixel (x=1)
        // srcX = 1 / (3/2) = 0.666...
        // Expecting a mix: ~33% Black and ~66% White -> Approx 170 brightness
        int resultPixel = dest.getRGB(1, 0);
        int red = (resultPixel >> 16) & 0xFF;

        // We accept a range because floating point math varies slightly
        assertTrue(red > 100 && red < 200, "Middle pixel should be a blend (gray), but got: " + red);
    }

    @Test
    void downscale_ShouldHandleUniformColor() {
        // If image is all Blue, result must be Blue
        source = new BufferedImage(10, 10, BufferedImage.TYPE_INT_ARGB);
        for(int x=0; x<10; x++)
            for(int y=0; y<10; y++)
                source.setRGB(x, y, 0xFF0000FF); // Blue

        dest = new BufferedImage(5, 5, BufferedImage.TYPE_INT_ARGB);

        interpolator.downscale(source, dest);

        assertEquals(0xFF0000FF, dest.getRGB(0,0), "Uniform color should not change");
    }
}