# **img2Brick \- Java Component**

This project serves as the central control unit for the Lego Tiling application. Its serves as the bridge between the Php frontend, the C tiling algorithm and the Stock management. I managed to do the bridge between this component and the C component, so it is already possible to import your own images and view the compressed and lego version.

## **Features**

* **Image Processing**: Custom implementation of Nearest Neighbor, Bilinear, and Bicubic downscaling algorithms.

* **Factory Client**: Robust REST client handling authentication, automatic redirects, and JSON parsing (Gson).

* **Proof of Work and account refilling**: Automated "crypto-mining" module to refill factory credits by solving SHA-256 challenges.

* **Inventory Management**: Local SQL database (MariaDB) synchronization to track brick serial numbers and certificates.

* **C-Bridge**: ProcessBuilder integration to run the native C tiling engine and capture its output.


## **Prerequisites**

* **Java 17+**  
* **Maven** (for dependency management)  
* **MariaDB** (Local instance)  
* **GCC** (to compile the C engine)


## **Setup**

1. **Database**:  
   * Create a local database named lego\_db.  
   * Import the provided dump.sql to set up tables (inventory, catalog, etc.).  
   * Ensure your catalog\_with\_stock view is active.  
2. **Configuration**:  
   * Edit src/main/resources/config.properties:  
   * DB\_URL=jdbc:mariadb://localhost:3306/lego\_db  
   * DB\_USER=root  
   * DB\_PASSWORD=your\_password  
   * USER\_MAIL=[your\_email@univ-eiffel.fr](mailto:your_email@univ-eiffel.fr)  
   * API\_KEY=your\_factory\_api\_key  
   * 

## **Usage**

Run the main class fr.uge.univ\_eiffel.Main. The application pipeline will:

1. Downscale the source image (test\_imgs/original-image.jpg).  
2. Export the current SQL inventory to catalog.txt.  
3. Trigger the C Engine to calculate the optimal tiling.  
4. Mine credits (PoW) if the account balance is low.  
5. Parse the generated invoice and order missing bricks from the Factory API.  
6. Update the local database with the new brick certificates.  
   

## **Project Structure**

* fr.uge.univ\_eiffel  
  * downscalers/: Image resizing algorithms.  
  * payment\_methods/: Payment strategies (PoW).  
  * image\_processing/: File I/O and Hex Matrix conversion.  
  * App.java: Main application controller (Facade pattern).  
  * FactoryClient.java: HTTP REST Client.  
  * InventoryManager.java: DAO for MariaDB.

  