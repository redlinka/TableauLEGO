package fr.uge.univ_eiffel.image_processing;

import org.junit.jupiter.api.Test;
import org.junit.jupiter.api.io.TempDir;

import javax.imageio.ImageIO;
import java.awt.image.BufferedImage;
import java.io.File;
import java.io.IOException;
import java.nio.file.Files;
import java.nio.file.Path;

import static org.junit.jupiter.api.Assertions.*;

class LegoVisualizerTest {

    @TempDir
    Path tempDir;

    @Test
    void main_ShouldGeneratePng_FromValidLayout() throws IOException {
        // 1. ARRANGE
        // Create a fake input file representing the C program output
        // Format: Width-Height/Hex, Rotation, X, Y
        // Line 1: 2x4 Red Brick at (0,0), No Rotation
        // Line 2: 1x2 Blue Brick at (10,10), Rotated (becomes 2x1)
        String content = "2-4/FF0000, 0, 0, 0\n" +
                "1-2/0000FF, 1, 10, 10";

        File inputFile = tempDir.resolve("layout.txt").toFile();
        Files.writeString(inputFile.toPath(), content);

        // Define output path (The code will append ".png" to this!)
        File outputFile = tempDir.resolve("output").toFile();

        // 2. ACT
        String[] args = {
                inputFile.getAbsolutePath(),
                outputFile.getAbsolutePath()
        };

        // Run the main method (this drives the whole logic)
        LegoVisualizer.main(args);

        // 3. ASSERT
        // Check for "output.png" (not just "output")
        File expectedPng = new File(outputFile.getAbsolutePath() + ".png");

        assertTrue(expectedPng.exists(), "The visualizer should create a .png file");

        BufferedImage result = ImageIO.read(expectedPng);
        assertNotNull(result, "The output file should be a valid image");

        // Basic check: The image should be big enough to hold the bricks
        // Max coordinate is (10,10) + size. Scale is likely > 1.
        assertTrue(result.getWidth() > 10, "Image width should be substantial");
        assertTrue(result.getHeight() > 10, "Image height should be substantial");
    }

    @Test
    void main_ShouldSkipInvalidLines_WithoutCrashing() throws IOException {
        // ARRANGE
        // File with garbage lines
        String content = "THIS IS NOT A VALID LINE\n" +
                "2-4/FF0000, 0, 0, 0"; // At least one valid line to prevent System.exit(1)

        File inputFile = tempDir.resolve("mixed.txt").toFile();
        Files.writeString(inputFile.toPath(), content);
        File outputFile = tempDir.resolve("mixed_output").toFile();

        // ACT
        // Should not throw exceptions
        assertDoesNotThrow(() -> LegoVisualizer.main(new String[]{
                inputFile.getAbsolutePath(),
                outputFile.getAbsolutePath()
        }));

        // ASSERT
        // It should still produce an image for the one valid brick
        File expectedPng = new File(outputFile.getAbsolutePath() + ".png");
        assertTrue(expectedPng.exists());
    }
}