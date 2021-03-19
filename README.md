# toshi-magento-plugin

## Installation 
Install the module so that in appears within `app/code/Toshi/Shipping` folder

```
    php bin/magento setup:di:compile
    php bin/magento setup:upgrade
    php bin/magento cache:clear
```

**Attributes**

Setup Colour and Size attributes for products

Once that is done head to `Stores->Configuration->Sales->Shipping Methods` and fill in the Configuration for Toshi. 

**Configuration Values**

* Enabled -> Controls if shipping method is enabled 
* Mode -> Switch between `Try before you buy` or `Shipping`
* Title
* Method Name
* Environment -> Switch between pulling in Live or Staging JS
* Toshi Client API Keys + Server API Keys
* Colour and Size Attributes to be used on Toshi -> Used within the API and frontend to indicate to Toshi what is used for colour and sizes.
* Deffered Days -> Use for Deffer Shipping

**Create products**

Any new products created will have an `Available for Toshi` attribute ensure that is set to true for Products that are eliglible for Toshi. 

**Existing Products** 

For an existing catalogue where ALL products that will be sent to Toshi there is a console command.

```
    php bin/magento toshi:update:attribute
```

This will go through each product and set the attribute to true.

**Try Before you Buy**

To ensure Try Before you buy will show on the frontend, ensure that `Cash on Delivery` is enabled.  

**Deferred Shipping** 

There is a Toshi Deferred shipping attribute that can be set at config level to deffer the the options provided by Toshi for date selections.
e.g If there is a deffered date of 2 days on the config then available dates will be shown two days from today. 

If there is a specific product that has its own deffered date of 5 days the code will take those 5 days to provide dates to Toshi as that will override the config deferred dates.

Weekends and holidays defined in configuration as DD/MM are also skipped so if there is a deffered shipping of 1 day and the user is checking out on a Friday the next available date will be Tuesday as it will count the deffered date after the weekend. 
