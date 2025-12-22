package fr.uge.lego.paving;

import fr.uge.lego.brick.BrickColor;
import fr.uge.lego.brick.BrickType;
import fr.uge.lego.stock.StockItem;

import java.io.*;
import java.math.BigDecimal;
import java.nio.charset.StandardCharsets;
import java.util.ArrayList;
import java.util.List;

/**
 * Implementation of PavingEngine that calls the C program "pavage_v3".
 *
 * Expected executable API:
 *
 *    ./pavage_v3 pieces.txt image.txt out/pavage.out
 *
 * with stdout containing:
 *
 *    chemin_sortie prix_total qualite_totale ruptures
 */
public final class CProgramPavingEngine implements PavingEngine {

    private final String executablePath;

    public CProgramPavingEngine(String executablePath) {
        this.executablePath = executablePath;
    }

    @Override
    public List<PavingSolution> computePavings(PixelMatrix pixels, List<StockItem> stockItems) throws Exception {
        File imageFile = File.createTempFile("lego_image", ".txt");
        File piecesFile = File.createTempFile("lego_pieces", ".txt");
        File outFile = File.createTempFile("lego_pavage", ".out");

        writeImageFile(pixels, imageFile);
        writePiecesFile(stockItems, piecesFile);

        ProcessBuilder pb = new ProcessBuilder(
                executablePath,
                piecesFile.getAbsolutePath(),
                imageFile.getAbsolutePath(),
                outFile.getAbsolutePath()
        );
        pb.redirectErrorStream(true);
        Process process = pb.start();

        String summaryLine = null;
        try (BufferedReader reader = new BufferedReader(
                new InputStreamReader(process.getInputStream(), StandardCharsets.UTF_8))) {
            String line;
            while ((line = reader.readLine()) != null) {
                if (!line.trim().isEmpty()) {
                    summaryLine = line.trim();
                    break;
                }
            }
        }

        int exitCode = process.waitFor();
        if (exitCode != 0) {
            throw new IOException("pavage_v3 exited with code " + exitCode);
        }
        if (summaryLine == null) {
            throw new IOException("no summary line produced by pavage_v3");
        }

        String[] parts = summaryLine.split("\s+");
        if (parts.length < 4) {
            throw new IOException("invalid summary line: "" + summaryLine + """);
        }

        // String solutionPath = parts[0]; // not used yet
        double price = Double.parseDouble(parts[1]);
        double quality = Double.parseDouble(parts[2]);
        // int ruptures = Integer.parseInt(parts[3]); // could be stored later

        List<PlacedBrick> bricks = new ArrayList<>();
        PavingSolution solution = new PavingSolution(bricks, quality, BigDecimal.valueOf(price));
        List<PavingSolution> result = new ArrayList<>();
        result.add(solution);
        return result;
    }

    /**
     * Writes image in the text format expected by pavage_v3:
     *
     *   W H
     *   RRGGBB RRGGBB ...
     */
    private static void writeImageFile(PixelMatrix matrix, File file) throws IOException {
        try (PrintWriter writer = new PrintWriter(new OutputStreamWriter(
                new FileOutputStream(file), StandardCharsets.UTF_8))) {
            writer.println(matrix.width() + " " + matrix.height());
            for (int y = 0; y < matrix.height(); y++) {
                for (int x = 0; x < matrix.width(); x++) {
                    int rgb = matrix.get(x, y) & 0xFFFFFF;
                    writer.printf("%06X", rgb);
                    if (x + 1 < matrix.width()) {
                        writer.print(" ");
                    }
                }
                writer.println();
            }
        }
    }

    /**
     * Writes pieces in the text format expected by pavage_v3:
     *
     *   id w h r g b price stock
     */
    private static void writePiecesFile(List<StockItem> items, File file) throws IOException {
        try (PrintWriter writer = new PrintWriter(new OutputStreamWriter(
                new FileOutputStream(file), StandardCharsets.UTF_8))) {
            for (StockItem item : items) {
                BrickType type = item.type();
                BrickColor color = item.color();
                String id = (color.name() + "_" + type.name()).replace(' ', '_');
                int[] rgb = hexToRgb(color.hexCode());
                int r = rgb[0], g = rgb[1], b = rgb[2];
                double price = item.unitPrice().doubleValue();
                long stock = item.quantity();
                writer.printf("%s %d %d %d %d %d %.4f %d%n",
                        id, type.width(), type.length(), r, g, b, price, stock);
            }
        }
    }

    private static int[] hexToRgb(String hex) {
        String c = hex.startsWith("#") ? hex.substring(1) : hex;
        if (c.length() != 6) {
            throw new IllegalArgumentException("Invalid hex colour: " + hex);
        }
        int r = Integer.parseInt(c.substring(0, 2), 16);
        int g = Integer.parseInt(c.substring(2, 4), 16);
        int b = Integer.parseInt(c.substring(4, 6), 16);
        return new int[]{r, g, b};
    }
}
