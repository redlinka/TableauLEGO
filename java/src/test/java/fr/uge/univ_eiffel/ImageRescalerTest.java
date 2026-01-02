package fr.uge.univ_eiffel;

import org.junit.jupiter.api.Test;
import org.junit.jupiter.api.io.TempDir;

import javax.imageio.ImageIO;
import java.awt.image.BufferedImage;
import java.io.File;
import java.io.IOException;
import java.nio.file.Path;

import static org.junit.jupiter.api.Assertions.*;

class ImageRescalerTest {

    @TempDir
    Path tempDir;

    @Test
    void main_ShouldRescaleImage_WhenArgumentsAreValid() throws IOException {
        // 1. ARRANGE: Create a real 10x10 Green image on disk
        File inputFile = tempDir.resolve("input.png").toFile();
        File outputFile = tempDir.resolve("output.png").toFile();

        BufferedImage mockImage = new BufferedImage(10, 10, BufferedImage.TYPE_INT_ARGB);
        // Fill with Green
        for(int x=0; x<10; x++)
            for(int y=0; y<10; y++)
                mockImage.setRGB(x, y, 0xFF00FF00);

        ImageIO.write(mockImage, "png", inputFile);

        // 2. ACT: Call the main method with valid arguments
        // Usage: <input> <output> <width> <height> <algo>
        String[] args = {
                inputFile.getAbsolutePath(),
                outputFile.getAbsolutePath(),
                "5",  // Target Width
                "5",  // Target Height
                "nearest" // Algorithm
        };

        // This should NOT call System.exit(1) if successful
        ImageRescaler.main(args);

        // 3. ASSERT: Check if output file exists and has correct dimensions
        assertTrue(outputFile.exists(), "Output file should have been created");

        BufferedImage result = ImageIO.read(outputFile);
        assertNotNull(result, "Output image should be readable");
        assertEquals(5, result.getWidth(), "Width should be resized to 5");
        assertEquals(5, result.getHeight(), "Height should be resized to 5");
    }
}