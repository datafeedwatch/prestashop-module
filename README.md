# DataFeedWatch Module
**Basic rest:**
```
# if friendly url is enabled
yours-shop-address/datafeedwatch 

yours-shop-address/index.php?fc=module&module=datafeedwatch&controller=response
```
## Description of available parameters
- **token - parameter for authorization** # example value token=KTFM1SGuIFKaxntO
- **type - required param for set request type** # example value type=PRODUCTS
- **limit - specifies the max number of products to return. The default is 10** # example value limit=5
- **offset - specifies the number of products to skip. The default is 0** # example value offset=2
- **with_attributes - if set to 1, we get products list with product attributes** # example value with_attributes=1
- **product_id - parameter that specifies the product id** # example value product_id=12
- **lang - product language parameter** # example value lang=en
    
## Available requests
- **PRODUCTS** - Get Product List
    - Available Params:
        - token - required
        - # type - required
        - limit - not required
        - offset - not required
        - with_attributes - not required
        - lang - not required
    - Example request:
    ```
    # if friendly url is enabled
    yours-shop-address/datafeedwatch?with_attributes=1&limit=2&offset=2&token=KTFM1SGuIFKaxntO&lang=en&type=PRODUCTS
  
    yours-shop-address/index.php?fc=module&module=datafeedwatch&controller=response&with_attributes=1&limit=2&offset=2&token=KTFM1SGuIFKaxntO&lang=en&type=PRODUCTS
    ```

- **PRODUCTS_COUNT** - Get the number of products for the store
    - Available Params:
        - token - required
        - type - required
    - Example request:
    ```
    # if friendly url is enabled
    ours-shop-address/datafeedwatch?token=7bMfbZYkdqY6OOqD&type=PRODUCTS_COUNT
      
    yours-shop-address/index.php?fc=module&module=datafeedwatch&controller=response&token=7bMfbZYkdqY6OOqD&type=PRODUCTS_COUNT
    ```
  
- **PRODUCTS_ATTRIBUTES_COUNT** - The number of attribute variants for a given product
    - Available Params:
        - token - required
        - type - required
        - product_id - required
    - Example request:
    ```
    # if friendly url is enabled
    yours-shop-address/datafeedwatch?token=7bMfbZYkdqY6OOqD&type=PRODUCTS_ATTRIBUTES_COUNT&product_id=3
      
    yours-shop-address/index.php?fc=module&module=datafeedwatch&controller=response&token=7bMfbZYkdqY6OOqD&type=PRODUCTS_ATTRIBUTES_COUNT&product_id=3
    ```

- **VERSION** - Get module version and shop presta version
    - Available Params:
        - token - required
        - type - required
    - Example request:
    ```
    # if friendly url is enabled
    yours-shop-address/datafeedwatch?token=7bMfbZYkdqY6OOqD&type=VERSION
      
    yours-shop-address/index.php?fc=module&module=datafeedwatch&controller=response&token=7bMfbZYkdqY6OOqD&type=VERSION
    ```

- **LANGUAGES** - Get all active store languages
    - Available Params:
        - token - required
        - type - required
    - Example request:
    ```
    # if friendly url is enabled
    yours-shop-address/datafeedwatch?token=7bMfbZYkdqY6OOqD&type=LANGUAGES
      
    yours-shop-address/index.php?fc=module&module=datafeedwatch&controller=response&token=7bMfbZYkdqY6OOqD&type=LANGUAGES
    ```
