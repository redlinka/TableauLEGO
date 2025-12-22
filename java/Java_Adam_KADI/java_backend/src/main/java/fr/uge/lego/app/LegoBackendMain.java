package fr.uge.lego.app;

import fr.uge.lego.factory.FactoryConfig;
import fr.uge.lego.factory.LegoFactoryClient;
import fr.uge.lego.factory.dto.BillingBalance;
import fr.uge.lego.factory.dto.Challenge;
import fr.uge.lego.factory.pow.ProofOfWorkSolver;
import fr.uge.lego.image.*;

import javax.imageio.ImageIO;
import java.awt.image.BufferedImage;
import java.io.File;

/**
 * Small command-line interface to exercise the Java backend.
 */
public final class LegoBackendMain {

    private LegoBackendMain() {}

    private static void usage() {
        System.out.println("Usage:");
        System.out.println("  resize <source> <dest> <width>x<height> <nearest|bilinear|bicubic>");
        System.out.println("  ping-factory");
        System.out.println("  solve-challenge");
    }

    public static void main(String[] args) throws Exception {
        if (args.length == 0) {
            usage();
            return;
        }
        switch (args[0]) {
            case "resize" -> resizeCommand(args);
            case "ping-factory" -> pingFactoryCommand();
            case "solve-challenge" -> solveChallengeCommand();
            default -> usage();
        }
    }

    private static void resizeCommand(String[] args) throws Exception {
        if (args.length != 5) {
            usage();
            return;
        }
        File srcFile = new File(args[1]);
        File dstFile = new File(args[2]);
        String[] dims = args[3].split("x");
        int width = Integer.parseInt(dims[0]);
        int height = Integer.parseInt(dims[1]);
        String strategyName = args[4].toLowerCase();

        ResolutionChanger changer = switch (strategyName) {
            case "nearest" -> new NearestNeighborResolutionChanger();
            case "bilinear" -> new BilinearResolutionChanger();
            case "bicubic" -> new BicubicResolutionChanger();
            default -> throw new IllegalArgumentException("Unknown strategy: " + strategyName);
        };

        BufferedImage src = ImageIO.read(srcFile);
        if (src == null) {
            throw new IllegalArgumentException("Cannot read image " + srcFile);
        }
        BufferedImage dst = new BufferedImage(width, height, BufferedImage.TYPE_INT_RGB);
        changer.convert(src, dst);

        String name = dstFile.getName();
        int dot = name.lastIndexOf('.');
        if (dot < 0) {
            throw new IllegalArgumentException("Destination file must have an extension");
        }
        String format = name.substring(dot + 1);
        ImageIO.write(dst, format, dstFile);
        System.out.println("Resized image written to " + dstFile.getAbsolutePath());
    }

    private static void pingFactoryCommand() throws Exception {
        LegoFactoryClient client = new LegoFactoryClient(FactoryConfig.fromEnv());
        String response = client.ping();
        System.out.println("Ping response: " + response);
    }

    private static void solveChallengeCommand() throws Exception {
        LegoFactoryClient client = new LegoFactoryClient(FactoryConfig.fromEnv());
        Challenge challenge = client.fetchBillingChallenge();
        System.out.println("Challenge: " + challenge);
        ProofOfWorkSolver solver = new ProofOfWorkSolver();
        byte[] answer = solver.solve(challenge);
        System.out.println("Answer found, sending to server...");
        var result = client.sendBillingChallengeAnswer(challenge, answer);
        System.out.println("Server replied with: " + result);
        BillingBalance balance = client.getBalance();
        System.out.println("New balance: " + balance);
    }
}
