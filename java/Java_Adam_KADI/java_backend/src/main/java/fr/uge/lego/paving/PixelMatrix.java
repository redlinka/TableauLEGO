package fr.uge.lego.paving;

/**
 * Simple immutable matrix of pixels (RGB int).
 */
public final class PixelMatrix {
    private final int width;
    private final int height;
    private final int[][] pixels; // [y][x]

    public PixelMatrix(int width, int height, int[][] pixels) {
        if (width <= 0 || height <= 0) {
            throw new IllegalArgumentException("width and height must be positive");
        }
        this.width = width;
        this.height = height;
        this.pixels = new int[height][width];
        for (int y = 0; y < height; y++) {
            if (pixels[y].length < width) {
                throw new IllegalArgumentException("row " + y + " is too short");
            }
            System.arraycopy(pixels[y], 0, this.pixels[y], 0, width);
        }
    }

    public int width() { return width; }
    public int height() { return height; }

    public int get(int x, int y) {
        return pixels[y][x];
    }
}
