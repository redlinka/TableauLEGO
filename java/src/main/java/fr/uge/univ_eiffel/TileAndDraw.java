
package fr.uge.univ_eiffel;

import fr.uge.univ_eiffel.image_processing.ImageUtils;
import fr.uge.univ_eiffel.image_processing.LegoVisualizer;
import fr.uge.univ_eiffel.mediators.InventoryManager;

import java.awt.image.BufferedImage;
import java.io.*;
import java.util.ArrayList;
import java.util.List;
import java.util.UUID;

public class TileAndDraw {
    /**
     * A specialized tool for the new C tiler with automatic catalog generation.
     * Usage: java TileAndDraw <inputPng> <outputPng> <outputTxt> <exe> <algo> <mode> [algo_args...]
     *
     * Algorithms and their arguments:
     * - 1x1: No extra args
     * - quadtree: <threshold>
     * - tile: <width> <height> <threshold>
     *
     * Modes: strict | relax
     */
    public static void main(String[] args) {
        if (args.length < 6) {
            System.err.println("Usage: java TileAndDraw <inputPng> <outputPng> <outputTxt> <exe> <algo> <mode> [algo_args...]");
            System.err.println("Algorithms:");
            System.err.println("  1x1        (No extra args)");
            System.err.println("  quadtree   <threshold>");
            System.err.println("  tile       <width> <height> <threshold>");
            System.err.println("Modes: strict | relax");
            System.exit(1);
        }

        String inputPng      = args[0];
        String outputPng     = args[1];
        String outputTxt     = args[2];
        String exe           = args[3];
        String algo          = args[4];
        String mode          = args[5];

        // Validate mode
        if (!mode.equals("strict") && !mode.equals("relax")) {
            System.err.println("Error: Mode must be 'strict' or 'relax'. Got '" + mode + "'.");
            System.exit(1);
        }

        // Validate algorithm arguments
        if (algo.equals("quadtree") && args.length < 7) {
            System.err.println("Error: 'quadtree' requires a threshold argument.");
            System.exit(1);
        }
        if (algo.equals("tile") && args.length < 9) {
            System.err.println("Error: 'tile' requires width, height, and threshold arguments.");
            System.exit(1);
        }

        // Temporary files that will be cleaned up
        File tempHex = null;
        File tempCatalog = null;

        try {
            // 1. Create temporary directory and files
            String randomId = UUID.randomUUID().toString().replace("-", "");
            File tempDir = new File(System.getProperty("java.io.tmpdir"));

            // Create temporary hex matrix file
            tempHex = new File(tempDir, "hex_" + randomId + ".txt");
            BufferedImage img = ImageUtils.imageToBuffered(new File(inputPng));
            try (PrintWriter writer = ImageUtils.bufferedToHexMatrix(tempHex.getAbsolutePath(), img)) {
                // Writer is automatically closed
            }

            // 2. Create temporary catalog file using InventoryManager
            tempCatalog = new File(tempDir, "catalog_" + randomId + ".txt");
            System.out.println("Generating temporary catalog snapshot...");

            try (InventoryManager inventory = InventoryManager.makeFromProps("config.properties")) {
                // Export catalog to temporary file (removes .txt extension first since exportCatalog adds it)
                String catalogBasePath = tempCatalog.getAbsolutePath();
                String actualCatalogPath = inventory.exportCatalog(catalogBasePath);
                tempCatalog = new File(actualCatalogPath);
                System.out.println("Catalog exported to: " + actualCatalogPath);
            }

            // 3. Build C Command: <exe> <hex_image> <catalog> <output_file> <algo> <mode> [args...]
            List<String> command = new ArrayList<>();
            command.add(exe);
            command.add(tempHex.getAbsolutePath());
            command.add(tempCatalog.getAbsolutePath());
            command.add(outputTxt);  // Where C will write the solution
            command.add(algo);
            command.add(mode);

            // Add algorithm-specific arguments
            if (algo.equals("quadtree")) {
                command.add(args[6]); // threshold
            } else if (algo.equals("tile")) {
                command.add(args[6]); // width
                command.add(args[7]); // height
                command.add(args[8]); // threshold
            }
            // For 1x1, no extra arguments needed

            // 4. Run C Program
            System.out.println("Running C tiler with command: " + String.join(" ", command));
            ProcessBuilder pb = new ProcessBuilder(command);
            pb.redirectErrorStream(true);

            Process p = pb.start();

            // Read and display C program output
            try (BufferedReader reader = new BufferedReader(new InputStreamReader(p.getInputStream()))) {
                String line;
                while ((line = reader.readLine()) != null) {
                    System.out.println("[C Tiler] " + line);
                }
            }

            int code = p.waitFor();
            if (code != 0) {
                System.err.println("C Program failed with exit code " + code);
                System.exit(code);
            }

            // 5. Check if solution file was created
            File solutionFile = new File(outputTxt);
            if (!solutionFile.exists() || solutionFile.length() == 0) {
                System.err.println("Error: C program did not generate a valid solution file.");
                System.exit(1);
            }

            // 6. Visualize using the solution file generated by C
            System.out.println("Generating visualization...");
            LegoVisualizer.main(new String[]{ outputTxt, outputPng });

            System.out.println("SUCCESS: Tiling completed and visualization saved to " + outputPng + ".png");

        } catch (Exception e) {
            System.err.println("Error during execution:");
            e.printStackTrace();
            System.exit(1);
        } finally {
            // 7. Cleanup temporary files
            if (tempHex != null && tempHex.exists()) {
                if (tempHex.delete()) {
                    System.out.println("Cleaned up temporary hex file");
                } else {
                    System.err.println("Warning: Could not delete temporary hex file: " + tempHex.getAbsolutePath());
                }
            }

            if (tempCatalog != null && tempCatalog.exists()) {
                if (tempCatalog.delete()) {
                    System.out.println("Cleaned up temporary catalog file");
                } else {
                    System.err.println("Warning: Could not delete temporary catalog file: " + tempCatalog.getAbsolutePath());
                }
            }
        }
    }
}