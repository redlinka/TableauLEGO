package image_processing;

import java.awt.image.BufferedImage;
import java.io.*;
import java.util.ArrayList;
import java.util.List;
import java.util.UUID;

public class TileAndDraw {
    /**
     * A specialized tool just for the Web Interface.
     * Usage: java TileAndDraw <inPng> <outPng> <outTxt> <catalog> <exe> <method> <thresh>
     */
    public static void main(String[] args) {
        if (args.length != 7) {
            System.err.println("Usage: java TileAndDraw <inPng> <outPng> <outTxt> <catalog> <exe> <method> <thresh>");
            System.exit(1);
        }

        String inputPng      = args[0];
        String outputPng     = args[1];
        String outputBricks  = args[2];
        String catalog       = args[3];
        String exe           = args[4];
        String method        = args[5];
        String threshold     = args[6];

        try {
            // 1. Create Temp Hex Matrix (Input for C)
            String randomHex = UUID.randomUUID().toString().replace("-", "");
            File tempDir = new File(System.getProperty("java.io.tmpdir"));
            File tempHex = new File(tempDir, randomHex + ".txt");
            BufferedImage img = ImageUtils.imageToBuffered(new File(inputPng));
            // Use try-with-resources to ensure writer closes/flushes
            try (PrintWriter writer = ImageUtils.bufferedToHexMatrix(tempHex.getAbsolutePath(), img)) {}

            // 2. Build C Command Dynamically
            // Base command: ./C_tiler <hex> <catalog> <OUTPUT_FILE> <method>
            List<String> command = new ArrayList<>();
            command.add(exe);
            command.add(tempHex.getAbsolutePath());
            command.add(catalog);
            command.add(outputBricks); // C writes to this unique file
            command.add(method);

            // Conditional Argument: Only add threshold if Quadtree
            if ("quadtree".equalsIgnoreCase(method)) {
                command.add(threshold);
            }

            // 3. Run C Program
            ProcessBuilder pb = new ProcessBuilder(command);
            pb.redirectErrorStream(true);
            pb.redirectOutput(ProcessBuilder.Redirect.INHERIT); // Show C logs in PHP/Java console

            Process p = pb.start();
            int code = p.waitFor();

            if (code != 0) {
                System.err.println("C Program failed with code " + code);
                System.exit(code);
            }

            // 4. Visualize using the Brick File we just saved
            LegoVisualizer.main(new String[]{ outputBricks, outputPng });

            // Cleanup
            tempHex.delete();

            System.out.println("SUCCESS");

        } catch (Exception e) {
            e.printStackTrace();
            System.exit(1);
        }
    }
}
