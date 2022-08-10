let toshiLaunched = false;
let timeSlotSelected = false;
let additionalSizes = [];
let addressFields = [];

function launchToshi() {
  /** Prevent user going to the payment step if Toshi is selected */
  jQuery(":submit.continue").click((event) => {
    if (jQuery("[value=toshi_toshi]").is(":checked") && !timeSlotSelected) {
      event.preventDefault();
      showErrorMessage();
    }
  });

  /** Timeout user and redirect to cart if they don't complete their purchase in time */
  let timeout = checkoutConfig.toshiTimeout * 1000;

  setTimeout(() => {
    window.location.href = "/toshi/index/timeout";
  }, timeout);

  let config = {
    api: {
      url: checkoutConfig.toshiUrl,
      key: checkoutConfig.toshiKey,
    },
  };

  if (checkoutConfig.toshiMode == "try_before_you_buy") {
    config.ui = {
      mode: "tbyb-checkout",
    };
  }

  window.toshi.createBookingIntegration(config).then((modal) => {
    jQuery("#label_carrier_toshi_toshi").append('<div id="toshi-app"></div>');

    modal.mount(document.getElementById("toshi-app"));
    toshiLaunched = true;

    console.log("[Toshi] - Carrier Service added to DOM");

    // This is fired by the ecommerce integration when the customer attempts to
    // proceed without selecting a timeslot.
    window.showErrorMessage = () => {
      modal.setInlineErrorMessage(
        "Please select a time slot before proceeding"
      );
    };

    window.hideErrorMessage = () => {
      modal.setInlineErrorMessage(undefined);
    };

    modal.onOrderCreated(function () {
      timeSlotSelected = true;
      hideErrorMessage();
    });

    modal.setBrandCheckoutReference(checkoutConfig.quoteItemData[0].quote_id);

    modal.setOrderTotal({
      orderTotal: checkoutConfig.totalsData.base_row_total_incl_tax,
    });

    setTimeout(() => {
      /** Customer logged in and address exists */
      if (
        isCustomerLoggedIn &&
        Object.keys(customerData.addresses).length > 0
      ) {
        let addressIndex = jQuery(".shipping-address-items")
          .children(".selected-item")
          .index();

        /** Select Address */
        let address = Object.values(customerData.addresses)[addressIndex];

        modal.setFirstName(address.firstname);
        modal.setLastName(address.lastname);
        modal.setEmail(getCustomerEmail());
        modal.setPhone(address.telephone);
        modal.setAddress({
          addressLine1: address.street[0],
          addressLine2: address.street[1],
          town: address.city,
          province: address.region.region,
          postcode: address.postcode,
          country: address.country_id,
        });
      } else {
        /** Logged in customer with no existing address or guest customer */
        addressFields = [
          "input[name=street\\[0\\]]",
          "input[name=street\\[1\\]]",
          "input[name=city]",
          "input[name=region]",
          "input[name=postcode]",
        ];
        modal.setFirstName(jQuery("input[name=firstname]").val());
        modal.setLastName(jQuery("input[name=lastname]").val());
        modal.setEmail(getCustomerEmail());
        modal.setPhone(jQuery("input[name=telephone]").val());
        setAddress();

        jQuery(document).off("change", "input[name=firstname]");
        jQuery(document).on("change", "input[name=firstname]", function () {
          modal.setFirstName(jQuery("input[name=firstname]").val());
        });

        jQuery(document).off("change", "input[name=lasttname]");
        jQuery(document).on("change", "input[name=lastname]", function () {
          modal.setLastName(jQuery("input[name=lastname]").val());
        });

        jQuery(document).off("change", "#customer-email");
        jQuery(document).on("change", "#customer-email", function () {
          modal.setEmail(jQuery("#customer-email").val());
        });

        jQuery(document).off("change", "input[name=telephone]");
        jQuery(document).on("change", "input[name=telephone]", function () {
          modal.setPhone(jQuery("input[name=telephone]").val());
        });

        jQuery(document).off("change", addressFields);
        jQuery(document).on("change", addressFields, function () {
          setAddress();
        });
      }
    }, 750);

    function setAddress() {
      modal.setAddress({
        addressLine1: jQuery("input[name=street\\[0\\]]").val(),
        addressLine2: jQuery("input[name=street\\[1\\]]").val(),
        town: jQuery("input[name=city]").val(),
        province: jQuery("input[name=region]").val(),
        postcode: jQuery("input[name=postcode]").val(),
        country: jQuery("input[name=country_id]").val(),
      });
    }

    function createProduct(
      name,
      sku,
      qty,
      imageUrl,
      retailPrice,
      size,
      colour,
      availableSizes,
      availabilityType,
      availabilityDate
    ) {
      return {
        /** Mandatory Properties */
        name: name,
        size: size,
        sku: sku,
        quantity: qty,
        imageUrl: imageUrl,
        retailPrice: retailPrice,
        finalPrice: retailPrice,

        /** Optional Properties */
        colour: colour,
        availableSizes: availableSizes,

        availabilityType: availabilityType,
        availabilityDate: availabilityDate,
      };
    }

    let products = [];
    let sizeAttribute = checkoutConfig.toshiSizeAttribute;
    let colorAttribute = checkoutConfig.toshiColorAttribute;

    checkoutConfig.quoteItemData.forEach((item, index) => {
      if (item.product_type === "configurable") {
        availableSizes =
          checkoutConfig.toshiData.products[index].additionalSizes;
      } else {
        availableSizes = null;
      }

      availabilityType =
        checkoutConfig.toshiData.products[index].availabilityType;
      availabilityDate =
        checkoutConfig.toshiData.products[index].availabilityDate;

      products.push(
        createProduct(
          item.name,
          item.sku,
          item.qty,
          item.thumbnail,
          item.base_price_incl_tax,
          getAttribute(item, sizeAttribute),
          getAttribute(item, colorAttribute),
          availableSizes,
          availabilityType,
          availabilityDate
        )
      );
    });

    modal.setProducts(products);

    /** Get Attribute */
    function getAttribute(item, attributeType) {
      let attributeValue = "";
      if (attributeType) {
        item.options.forEach(function (option) {
          if (option["label"] == attributeType) {
            attributeValue = option["value"];
          }
        });
      }
      return attributeValue;
    }

    /** Get Customer Email */
    function getCustomerEmail() {
      if (isCustomerLoggedIn) {
        return customerData.email;
      } else {
        return jQuery("#customer-email").val();
      }
    }

    /** Get Container */
    function getContainerElement() {
      return document.getElementById("label_carrier_toshi_toshi");
    }

    /** Mount mutation observer waiting to initiate Toshi */
    MutationObserver = window.MutationObserver || window.WebKitMutationObserver;
    let obs = new MutationObserver(function (mutations, observer) {
      if (getContainerElement() && !toshiLaunched) {
        launchToshi();
      }

      if (
        getContainerElement() &&
        typeof modal != "undefined" &&
        jQuery("#toshi-app").length === 0
      ) {
        jQuery("#label_carrier_toshi_toshi").append(
          '<div id="toshi-app"></div>'
        );
        modal.mount(document.getElementById("toshi-app"));
      }
    });

    obs.observe(document.body, {
      attributes: true,
      childList: true,
      characterData: false,
      subtree: true,
    });
  });
}
