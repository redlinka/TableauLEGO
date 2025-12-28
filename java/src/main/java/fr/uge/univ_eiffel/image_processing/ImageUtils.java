package fr.uge.univ_eiffel.image_processing;

import javax.imageio.ImageIO;
import java.awt.image.BufferedImage;
import java.io.File;
import java.io.IOException;
import java.io.PrintWriter;

/** Utility class handling all the messy file I/O operations for images.
 * Responsible for converting between Files, BufferedImages, and the Hex Matrix format needed for the C engine.
 * Fields: None (Static utility class). */
public class ImageUtils {

    /** Loads an image file from disk into memory.
     * Input: The File object pointing to the source image.
     * Output: A BufferedImage ready for processing. */
    public static BufferedImage imageToBuffered(File input) throws IOException {

        if (!input.exists()) {
            throw new IOException("File does not exist: " + input.getAbsolutePath());
        }
        BufferedImage img = ImageIO.read(input);

        if (img == null) {
            throw new IOException("Failed to read image from file: " + input.getAbsolutePath());
        } else {
            return img;
        }
    }

    /** Converts pixel data into a formatted Hex Matrix text file.
     * This is the bridge format that the C program reads to understand the image.
     * Input: The desired output filename and the source BufferedImage.
     * Output: The active PrintWriter (though usually closed by the caller). */
    public static PrintWriter bufferedToHexMatrix(String name, BufferedImage img) throws IOException {

        if (name == null || name.isEmpty() || name == "null") {
            throw new IllegalArgumentException("name cannot be empty or null");
        }
        try (PrintWriter writer = new PrintWriter(name)) {

            int width = img.getWidth();
            int height = img.getHeight();
            writer.printf(width + " " + height + "\n");

            for (int y = 0; y < height; y++) {
                for (int x = 0; x < width; x++) {

                    // masks the alpha channel to keep only pure rgb values, maybe an ARGB version will be implemented in the future
                    int rgb = img.getRGB(x, y) & 0xFFFFFF; // keep only RGB
                    writer.printf("%06X", rgb);

                    if (x < width - 1) {
                        writer.print(" ");
                    }
                }
                writer.println();
            }
            return writer;

        } catch (IOException e) {
            throw new IOException("Failed to write image Hex to file");
        }
    }

    /** Saves a BufferedImage back to the disk as a PNG.
     * Input: Target file path and the image object.
     * Output: The File object of the saved image. */
    public static File bufferedToImage(String imagePath, BufferedImage img) throws IOException {

        if (imagePath == null || imagePath.isEmpty() || imagePath == "null") {
            throw new IllegalArgumentException("name cannot be empty or null");
        }
        File outputFile = new File(imagePath);

        if (ImageIO.write(img,"png",outputFile)) {
            return outputFile;
        } else {
            throw new IOException("Failed to write image to file: " + outputFile.getAbsolutePath());
        }
    }
}
