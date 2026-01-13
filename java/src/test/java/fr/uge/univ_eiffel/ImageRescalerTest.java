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
    void main_ShouldRescaleAndSave_WhenArgsAreValid() throws IOException {
        // 1. ARRANGE: Create a fake 10x10 Green image
        File inputFile = tempDir.resolve("input.png").toFile();

        // Note: We provide the base name "output".
        // Your code automatically adds ".png" and ".txt", so we expect "output.png"
        String outputBaseName = tempDir.resolve("output").toString();

        BufferedImage mockImage = new BufferedImage(10, 10, BufferedImage.TYPE_INT_ARGB);
        // Fill with Green color
        for(int x=0; x<10; x++)
            for(int y=0; y<10; y++)
                mockImage.setRGB(x, y, 0xFF00FF00);

        ImageIO.write(mockImage, "png", inputFile);

        // 2. ACT: Run the tool
        // Usage: <input> <output> <width> <height> <algo>
        String[] args = {
                inputFile.getAbsolutePath(),
                outputBaseName,
                "5",       // Target Width
                "5",       // Target Height
                "nearest"  // Algorithm
        };

        // This executes the main method. If it calls System.exit(1), the test will crash.
        // If it runs to completion (SUCCESS), the test continues.
        ImageRescaler.main(args);

        // 3. ASSERT: Verify files were created
        File expectedPng = new File(outputBaseName + ".png");
        File expectedTxt = new File(outputBaseName + ".txt");

        assertTrue(expectedPng.exists(), "Should create the resized PNG file");
        assertTrue(expectedTxt.exists(), "Should create the Hex Matrix TXT file");

        // Verify content dimensions
        BufferedImage result = ImageIO.read(expectedPng);
        assertNotNull(result);
        assertEquals(5, result.getWidth(), "Width should be resized to 5");
        assertEquals(5, result.getHeight(), "Height should be resized to 5");
    }
}