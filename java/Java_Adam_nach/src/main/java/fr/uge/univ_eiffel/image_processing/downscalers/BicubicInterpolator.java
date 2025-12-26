package fr.uge.univ_eiffel.image_processing.downscalers;

import java.awt.image.BufferedImage;

/** Implementation of bicubic interpolation for image resizing.
 * Provides higher quality results than linear methods but is computationally heavier.
 * Fields: None. */
public class BicubicInterpolator implements Downscaler {

    /** uses the bicubic interpolation formula to determine the best ARGB value of a given destination pixel during rescaling
     * Input: 4 color values and the distance 'd'.
     * Output: The interpolated color component value. */
    private double biCubicInterpolate(int c0,int c1, int c2, int c3, double d) {
        double slope1 = (c2 - c0)/2;
        double slope2 = (c3 - c1)/2;
        return (2 * Math.pow(d, 3) - 3 * Math.pow(d, 2) + 1) * c1
                + (Math.pow(d, 3) - 2 * Math.pow(d, 2) + d) * slope1
                + (-2 * Math.pow(d, 3) + 3 * Math.pow(d, 2)) * c2
                + (Math.pow(d, 3) - Math.pow(d, 2)) * slope2;
    }

    // implementation of bicubic interpolation rescaling, the difference with linear interpolation is really noceable at smaller or bigger scales
    // it tends to become very slow at bigger scales
    /** Resizes the image using a 4x4 pixel neighborhood.
     * Iterates through every destination pixel and computes color based on 16 surrounding source pixels.
     * Input: Source image and blank destination image.
     * Output: void (modifies destination). */
    public void downscale(BufferedImage source, BufferedImage destination) {
        double widthRatio = (double) destination.getWidth() / source.getWidth();
        double heightRatio = (double) destination.getHeight() / source.getHeight();

        for (int x = 0; x < destination.getWidth(); x++) {
            for (int y = 0; y < destination.getHeight(); y++) {

                double srcX = x / widthRatio;
                double srcY = y / heightRatio;

                int[] xPoints = new int[4];
                int[] yPoints = new int[4];
                for (int i = -1; i <= 2; i++) {
                    int index = i + 1;
                    xPoints[index] = clamp((int) Math.floor(srcX) + i, source.getWidth() - 1);
                    yPoints[index] = clamp((int) Math.floor(srcY) + i, source.getHeight() - 1);
                }

                double dx = srcX - xPoints[1]; // value btw 0 and 1 indicating the coords of the dest pixel in comparison to its neighbors
                double dy = srcY - yPoints[1]; // value btw 0 and 1 indicating the coords of the dest pixel in comparison to its neighbors

                int[][][] colors = new int[4][4][4];
                // we get the ARGB values of the 16 neighbours and put them in a neat 3D matrix (4 * 4 * nARGB)
                for (int i = 0; i < 4; i++) {
                    for (int j = 0; j < 4; j++) {
                        colors[i][j] = extractARGB(source.getRGB(xPoints[j], yPoints[i]));
                    }
                }

                // we do the 1D interpolation twice to get the 2D version
                int[] passY = new int[4];
                for (int j = 0; j < 4; j++) {
                    int[] passX = new int[4];
                    for (int i = 0; i < 4; i++) {
                        passX[i] = (int) biCubicInterpolate(colors[i][0][j], colors[i][1][j], colors[i][2][j], colors[i][3][j], dx);
                    }
                    passY[j] = clamp((int) biCubicInterpolate(passX[0], passX[1], passX[2], passX[3], dy), 255);
                }
                destination.setRGB(x, y, combineARGB(passY));
            }
        }
    }
}
