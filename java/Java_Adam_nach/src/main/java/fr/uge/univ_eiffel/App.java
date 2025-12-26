package fr.uge.univ_eiffel;

import fr.uge.univ_eiffel.image_processing.ImageUtils;
import fr.uge.univ_eiffel.image_processing.downscalers.Downscaler;
import fr.uge.univ_eiffel.payment_methods.PoW.PoWMethod;

import java.awt.image.BufferedImage;
import java.io.*;

/** The big class that orchestrates the entire application flow.
 * WARNING: This code is heavily tailored for MY local MariaDB setup.
 * It likely won't run on your machine unless you update 'config.properties to fit your DB' and import the SQL dump.
 * However, a pre-made 'catalog.txt' is included so the C engine can still run without the DB.
 * Fields: The 4 main modules (Client, Inventory, Orderer, Refiller). */
public class App {

    private final FactoryClient client;
    private final InventoryManager inventory;
    private final OrderManager orderer;
    private final PoWMethod refiller;

    static final String INPUT_PATH = "test_imgs_inputs\\";
    static final String OUTPUT_PATH = "test_imgs_outputs\\";

    private App(FactoryClient client, InventoryManager inventory, OrderManager orderer, PoWMethod refiller) {
        this.client = client;
        this.inventory = inventory;
        this.orderer = orderer;
        this.refiller = refiller;
    }

    /** setups the entire app structure from a configuration file.
     * Input: Path to properties file (DB credentials, API keys).
     * Output: A ready-to-use App instance. */
    public static App initialize(String configFile) throws Exception {
        FactoryClient client = FactoryClient.makeFromProps(configFile);
        InventoryManager inventory = InventoryManager.makeFromProps(configFile);
        OrderManager orderer = new OrderManager(client, inventory);
        PoWMethod refiller = new PoWMethod(client);

        return new App(client, inventory, orderer, refiller);
    }

    /** The main pipeline execution.
     * Steps: Process Image -> Dump DB -> Run C Engine -> Refill Credits -> Buy Bricks.
     * Input: Image paths, scaling method, dimensions, refill amount, and variance threshold.
     * Output: void. */
    public void run(String imagePath, Downscaler method, String imageName, int width, int height, double refillAmount, int threshold) throws Exception {
        processImage(INPUT_PATH + imagePath, method, imageName, width, height);
        exportInventory(INPUT_PATH + "catalog.txt");
        runCTiler( OUTPUT_PATH + imageName + ".txt", INPUT_PATH + "catalog.txt", threshold);
        refillAccount(refillAmount);
        handleOrders("order_quadtree.txt");
        inventory.close();
    }

    /** Handles the image downscaling and hex matrix generation.
     * Input: Source path, algo, output name, and target resolution.
     * Output: void (Saves PNG and TXT files). */
    private void processImage(String inputPath,  Downscaler method, String outputName, int inWidth, int outHeight) throws Exception {
        try {
            System.out.println("Processing image...");

            File input = new File(inputPath);

            BufferedImage src = ImageUtils.imageToBuffered(input);
            BufferedImage dest = new BufferedImage(inWidth, outHeight, BufferedImage.TYPE_INT_ARGB);
            method.downscale(src, dest);

            ImageUtils.bufferedToImage(OUTPUT_PATH + outputName + ".png", dest);
            ImageUtils.bufferedToHexMatrix(OUTPUT_PATH + outputName + ".txt", dest);
            System.out.println("image matrix created in " + outputName + ".txt");

        } catch (Exception e) {
            System.err.println("Image processing failed:");
            e.printStackTrace();
        }
    }

    /** Snapshots the current DB state into a text file for the C engine.
     * Input: Target file path.
     * Output: void (Writes catalog.txt). */
    private void exportInventory(String filePath) throws Exception {
        inventory.exportCatalog(filePath);
        System.out.println("Catalog exported to catalog.txt");
    }

    /** Wakes up the compiled C executable to perform the tiling optimization.
     * Input: Paths to the hex matrix and catalog, plus the variance threshold.
     * Output: The console output from the C program as a String. */
    private String runCTiler(String hexMatrixPath, String catalogPath, int threshold) throws Exception {

        String exePath = ".\\C_tiler.exe";

        ProcessBuilder tiler = new ProcessBuilder(
                exePath,
                hexMatrixPath,
                catalogPath,
                String.valueOf(threshold)
        );
        // merge stderr into stdout so we don't miss any crash logs
        tiler.redirectErrorStream(true);
        Process process = tiler.start();

        StringBuilder output = new StringBuilder();
        try (BufferedReader reader = new BufferedReader(new InputStreamReader(process.getInputStream()))) {
            String line;
            while ((line = reader.readLine()) != null) {
                System.out.println("[C-TILER] " + line); // For debugging
                output.append(line).append("\n");
            }
        }
        int code = process.waitFor();
        if (code != 0) {
            throw new RuntimeException("C program exited with code " + code);
        }

        return output.toString();
    }

    /** Wrapper to print money via Proof of Work.
     * Input: Amount of credits needed.
     * Output: void. */
    private void refillAccount(double refillAmount) throws IOException {
        System.out.println("Refilling account...");
        refiller.pay(refillAmount);
        System.out.println("Balance is now: " + client.balance());
    }


    /** The shopping logic. Reads the C order signaling and buys missing bricks.
     * Input: Path to the missing bricks file generated by the C program.
     * Output: void (Updates DB with new bricks). */
    private void handleOrders(String invoicePath) throws Exception {

        var invoice = orderer.parseInvoice(invoicePath);
        if (invoice == null || invoice.isEmpty()) {
            System.out.println("No invoice detected. Skipping order.");
            return;
        }

        System.out.println("Invoice parsed: " + invoice);

        var quote = orderer.requestQuote(invoice);
        System.out.println("currently asking confirmation of quote: " + quote);

        orderer.confirmOrder(quote.id());

        OrderManager.Delivery status;
        do {
            //we check every 500 millisecs
            Thread.sleep(500);
            status = orderer.deliveryStatus(quote.id());
            System.out.println("pending bricks :" + status.pendingBricks());
        } while (!status.completed());

        System.out.println("Order completed. Adding bricks...");

        for (Brick brick : status.bricks()) {
            boolean valid = client.verify(brick.name(), brick.serial(), brick.certificate());
            boolean added = inventory.add(brick);

            if (valid && added) {
                System.out.println("Brick " + brick.name() + " added to inventory");
            } else {
                System.out.println("Brick " + brick.name() + " failed verification or already exists");
            }
        }
    }
}
