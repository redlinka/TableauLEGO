package fr.univ_eiffel.pixelisation;

import java.io.File;
import java.io.IOException;
import java.awt.image.BufferedImage;
import java.sql.*;
import javax.imageio.ImageIO;

public class Main {

    /**
     * Usage: java ClassName pictureId widthxheight method
     */
    public static void main(String[] args) throws IOException {
        if (args.length < 3) {
            System.err.println("Usage: java Main <pictureId> <widthxheight> <method>");
            return;
        }
        int pictureId = Integer.parseInt(args[0]);
        var destinationDimensions = args[1].split("x"); // width and height are separated by a x char
        var destinationWidth = Integer.parseInt(destinationDimensions[0]);
        var destinationHeight = Integer.parseInt(destinationDimensions[1]);
        String methodName = args[2];

        try (Connection conn = MySQLConnection.getConnection();
            Statement s = conn.createStatement()) {

            s.execute("SELECT * from picture WHERE id = " + args[0]);
            ResultSet rs = s.getResultSet();
            if (!rs.next()) {
                System.err.println("No picture found with id = " + pictureId);
                return;
            }

            byte[] imageData = rs.getBytes("image_data");
            var source = ImageIO.read(new java.io.ByteArrayInputStream(imageData));

            if (source == null)
                throw new IOException("The source file cannot be decoded");
            var destination = new BufferedImage(destinationWidth, destinationHeight, BufferedImage.TYPE_INT_RGB);
            var method = PixelizationFactory.get(methodName);
            method.pixelize(source, destination);

            // Build LONGTEXT pixel representation
            StringBuilder sb = new StringBuilder();
            sb.append(destinationWidth).append(" ").append(destinationHeight).append("\n");

            for (int y = 0; y < destinationHeight; y++) {
                for (int x = 0; x < destinationWidth; x++) {
                    int rgb = destination.getRGB(x, y) & 0xFFFFFF;
                    sb.append(String.format("%06X", rgb)).append(" ");
                }
                sb.append("\n");
            }

            // Insert into pixelized_picture
            PreparedStatement ps = conn.prepareStatement("INSERT INTO pixelized_picture (picture_id, method, pixels) VALUES (?, ?, ?)");
            ps.setInt(1, pictureId);
            ps.setString(2, methodName);
            ps.setString(3, sb.toString());
            ps.executeUpdate();

            // Test

            var destinationFile = new File("/home/user/Pictures/result.jpg");
            var fileElements = destinationFile.getName().split("\\.");
            var fileExtension = fileElements[fileElements.length-1]; // last chunk of the name after the dot ImageIO.write(destination, fileExtension, destinationFile);
            ImageIO.write(destination, fileExtension, destinationFile);
            System.out.println("Pixelized image saved in DB for picture_id " + pictureId);

        } catch (SQLException e) {
            System.err.println("SQL Error : " + e.getMessage());
        }
    }
}
