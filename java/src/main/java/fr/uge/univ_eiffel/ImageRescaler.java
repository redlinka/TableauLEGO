package fr.uge.univ_eiffel;

import fr.uge.univ_eiffel.image_processing.ImageUtils;
import fr.uge.univ_eiffel.image_processing.downscalers.*;

import java.awt.image.BufferedImage;
import java.io.File;

/**
 * A specialized tool just for the Web Interface.
 * Usage: java ImageScaler <inputPath> <outputPath> <width> <height> <algo>
 */
public class ImageRescaler {

    public static void main(String[] args) {
        if (args.length != 5) {
            System.err.println("Usage: <inputPath> <outputPath> <width> <height> <algo>");
            System.exit(1);
        }

        String inputPath = args[0];
        String outputPath = args[1];
        int width = Integer.parseInt(args[2]);
        int height = Integer.parseInt(args[3]);
        String algoName = args[4].toLowerCase();

        try {
            // 1. Select the Algorithm dynamically
            Downscaler method;
            switch (algoName) {
                case "nearest": method = new NearestNeighbour(); break;
                case "bicubic": method = new BicubicInterpolator(); break;
                case "bilinear": method = new BilinearInterpolator(); break;
                default:
                    System.err.println("Unknown algorithm: " + algoName);
                    System.exit(1);
                    return;
            }

            // 2. Load Image
            File input = new File(inputPath);
            BufferedImage src = ImageUtils.imageToBuffered(input);

            // 3. Process (The core logic extracted from App.java)
            BufferedImage dest = new BufferedImage(width, height, BufferedImage.TYPE_INT_ARGB);
            method.downscale(src, dest);


            // 4. Save
            ImageUtils.bufferedToImage(outputPath + ".png", dest);
            ImageUtils.bufferedToHexMatrix(outputPath +".txt", dest);
            System.out.println("SUCCESS");

        } catch (Exception e) {
            e.printStackTrace();
            System.exit(1);
        }
    }
}
