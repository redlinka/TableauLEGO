package fr.uge.univ_eiffel;

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
        // REMOVE SPACES after commas!
        // Format: Name,X,Y  (Not Name, X, Y)
        String content = "2-4-Red/FF0000,10,10\n" +
                "1-1-Blue/0000FF,50,50";

        File inputFile = tempDir.resolve("layout.txt").toFile();
        Files.writeString(inputFile.toPath(), content);

        File outputFile = tempDir.resolve("output.png").toFile();

        // 2. ACT
        String[] args = {
                inputFile.getAbsolutePath(),
                outputFile.getAbsolutePath()
        };

        LegoVisualizer.main(args);

        // 3. ASSERT
        assertTrue(outputFile.exists(), "PNG file should be created");

        BufferedImage result = ImageIO.read(outputFile);
        assertNotNull(result);
        assertTrue(result.getWidth() > 0);
    }
}