package fr.uge.lego.matrix;

import java.awt.Color;
import java.awt.image.BufferedImage;
import java.util.Objects;

public class Matrix {

	private Color[][] matrix;
	private int height, width;

	public Matrix(int height, int width) {
		// original image size
		this.height = height;
		this.width = width;
	}

	public Color[][] getMatrix() {
		return matrix;
	}

	public void nearestNeighborInterpolation(BufferedImage image, int targetHeight, int targetWidth) {
		Objects.requireNonNull(image);
		if (targetHeight < 1 || targetWidth < 1) {
			throw new IllegalArgumentException("Targeted resolution must be superior than 0");
		}

		height = targetHeight;
		width = targetWidth;

		double heightRatio = (double) image.getHeight() / targetHeight;
		double widthRatio = (double) image.getWidth() / targetWidth;

		matrix = new Color[targetHeight][targetWidth];

		for (int i = 0; i < targetHeight; i++) {
			for (int j = 0; j < targetWidth; j++) {

				// Find the indexes in the original image
				int x = (int) (j * widthRatio);
				int y = (int) (i * heightRatio);

				// prevent index error
				x = Math.min(x, image.getWidth() - 1);
				y = Math.min(y, image.getHeight() - 1);

				Color color = new Color(image.getRGB(x, y));
				matrix[i][j] = new Color(color.getRGB());
			}
		}
	}

	public void bilinearInterpolation(BufferedImage image, int targetHeight, int targetWidth) {
		Objects.requireNonNull(image);

		height = targetHeight;
		width = targetWidth;

		double heightRatio = (double) image.getHeight() / targetHeight;
		double widthRatio = (double) image.getWidth() / targetWidth;

		matrix = new Color[targetHeight][targetWidth];

		for (int i = 0; i < targetHeight; i++) {
			for (int j = 0; j < targetWidth; j++) {

				// Find the indexes in the original image
				int x1 = (int) (j * widthRatio);
				int y1 = (int) (i * heightRatio);
				int x2 = Math.min(x1 + 1, image.getWidth() - 1);
				int y2 = Math.min(y1 + 1, image.getHeight() - 1);

				Color color = new Color(image.getRGB(x1, y1));
				Color color2 = new Color(image.getRGB(x2, y1));
				Color color3 = new Color(image.getRGB(x1, y2));
				Color color4 = new Color(image.getRGB(x2, y2));

				// calcul des moyennes des couleurs
				int sumR = (color.getRed() + color2.getRed() + color3.getRed() + color4.getRed()) / 4;
				int sumG = (color.getGreen() + color2.getGreen() + color3.getGreen() + color4.getGreen()) / 4;
				int sumB = (color.getBlue() + color2.getBlue() + color3.getBlue() + color4.getBlue()) / 4;
				int sumA = (color.getAlpha() + color2.getAlpha() + color3.getAlpha() + color4.getAlpha()) / 4;

				matrix[i][j] = new Color(sumR, sumG, sumB, sumA);
			}
		}
	}

	public void bicubicInterpolation(BufferedImage image, int targetHeight, int targetWidth) {
		Objects.requireNonNull(image);

		double heightRatio = (double) image.getHeight() / targetHeight;
		double widthRatio = (double) image.getWidth() / targetWidth;

		height = targetHeight;
		width = targetWidth;
		matrix = new Color[targetHeight][targetWidth];

		double[][] weights = { { 1, 2, 2, 1 }, { 2, 4, 4, 2 }, { 2, 4, 4, 2 }, { 1, 2, 2, 1 } };

		double weightSum = 36.0; // somme des poids

		for (int x = 0; x < targetHeight; x++) {
			for (int y = 0; y < targetWidth; y++) {
				double fx = (y + 0.5) * widthRatio - 0.5;
				double fy = (x + 0.5) * heightRatio - 0.5;

				int xInt = (int) fx;
				int yInt = (int) fy;

				double red = 0;
				double green = 0;
				double blue = 0;
				double alpha = 0;

				for (int m = -1; m <= 2; m++) {
					for (int n = -1; n <= 2; n++) {
						int px = Math.min(Math.max(xInt + m, 0), image.getWidth() - 1);
						int py = Math.min(Math.max(yInt + n, 0), image.getHeight() - 1);
						Color c = new Color(image.getRGB(px, py), true);
						double w = weights[m + 1][n + 1];

						red += c.getRed() * w;
						green += c.getGreen() * w;
						blue += c.getBlue() * w;
						alpha += c.getAlpha() * w;
					}
				}

				// Normalisation des valeurs des couleurs
				red = Math.min(255, Math.max(0, red / weightSum));
				green = Math.min(255, Math.max(0, green / weightSum));
				blue = Math.min(255, Math.max(0, blue / weightSum));
				alpha = Math.min(255, Math.max(0, alpha / weightSum));

				Color color = new Color((int) red, (int) green, (int) blue, (int) alpha);

				matrix[x][y] = new Color(color.getRGB());
			}
		}
	}

	void printMatrix() {
		for (int i = 0; i < height; i++) {
			for (int j = 0; j < width; j++) {
				System.out.print(matrix[i][j] + ", ");
			}
			System.out.println();
		}
	}

	public BufferedImage setMatrixToImage() {
		BufferedImage bufferedImage = new BufferedImage(width, height, 2);
		for (int i = 0; i < height; i++) {
			for (int j = 0; j < width; j++) {
				Color color = matrix[i][j];
				Color rgb = new Color(color.getRed(), color.getGreen(), color.getBlue(), color.getAlpha());
				bufferedImage.setRGB(j, i, rgb.getRGB());
			}
		}
		return bufferedImage;
	}
}
