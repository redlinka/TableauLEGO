package fr.uge.univ_eiffel.image_processing.downscalers;

import org.junit.jupiter.api.Test;
import java.awt.image.BufferedImage;
import static org.junit.jupiter.api.Assertions.*;

class BilinearInterpolatorTest {

    @Test
    void downscale_ShouldBlendColors_OnUpscale() {
        // ARRANGE: 2x1 Image [Black | White]
        BufferedImage source = new BufferedImage(2, 1, BufferedImage.TYPE_INT_ARGB);
        source.setRGB(0, 0, 0xFF000000); // Black (0)
        source.setRGB(1, 0, 0xFFFFFFFF); // White (255)

        BilinearInterpolator algo = new BilinearInterpolator();

        // ACT: Upscale to 3x1 to force the middle pixel to sit "between" the original two
        BufferedImage dest = new BufferedImage(3, 1, BufferedImage.TYPE_INT_ARGB);
        algo.downscale(source, dest);

        // ASSERT: The middle pixel (index 1) should be a gray blend
        int middlePixel = dest.getRGB(1, 0);
        int red = (middlePixel >> 16) & 0xFF;

        // Pure black is 0, Pure white is 255. We expect something in between (~50-200).
        assertTrue(red > 50 && red < 200, "Middle pixel should be a gray blend, but got: " + red);
    }
}