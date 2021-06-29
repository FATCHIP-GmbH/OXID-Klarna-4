### 4.6.2
* Fix spelling on new Klarna admin start page DE/EN
* Bugfix: Shipping costs add up

### 4.6.1
* Fix conflicts with payone module

### 4.6.0
* Add Klarna Support for new countries: IT, ES, FR and BE

### 4.5.0
* Packstation implementation for KCO
* Improved configuration options for a B2B and B2C store
* oxDiscout object with negative oxPrice value transferred as surcharge to Klarna API
* Word "Klarna" removed from payment method name on user views: order overview, email
* Improved logging for patch order request

### 4.4.4
* Change Klarna Contact information
* Bugfix: Don't show KCO country selector popup when only one country is active
* Fix Javascript problem when custom theme is in use
* Rename Klarna Payment methods
* Update Pay Now and Pay later translation

### 4.4.3
* Fix compatibility issues with old php versions
* refactoring of order execution for Klarna Payments
* PayPal fixes for salutation, company name and care-of fields
* rename slice it to financing
* update Klarna merchant portal URLs

### 4.4.2
* Minor bug fix klarna order Registry
* Klarna General Settings EE shopid fix
* KCO Buy now button fix when "Select action when product is added to cart" setting is set to "None"

### 4.4.1
* EU Geoblocking regulation implementation
* KCO: Bugfix for Country list drop-down
* Remove empty check on klarnaToOxid formatter
* correct image path for actions
* KP Pay Now split: Fix admin configuration bug

### 4.4.0
* Added support for KP split and combined mode
* Added support for KP in CH
* Klarna Logs BugFix

### 4.3.0
* Added B2B to KCO

### 4.2.2
* Added street_address2/oxaddinfo mapping so c/o or company names will be transmitted from Klarna Checkout to OXID eShop
* Prefill KCO care_of field so oxaddinfo will be transmitted from OXID eShop to Klarna Checkout

### 4.2.1
* BugFix on admin KlarnaConfiguration page in KP mode: Changes were not saved due to wrong cl parameter
* BugFix admin/GeneralSettings credentials, country select options fix
* BugFix klarna_express::resolveUser - wrong method called on user object
* support for older php versions improved
* fixed issue when general terms checkbox was active
* BugFix 0 payment price for oxid 4.9 and older
* onDeactivate method removed. BugFix for EE
* fixed http client signature
* core/KlarnaPayment.php compatibility for older oxid platforms

### 4.2.0
* Link BugFix for EE
* Design changes in the admin panel

### 4.1.0
* Added KP B2B feature
* Fixed issue about Amazon Payments integration

### 4.0.1
* Applied some changes to meet OXID module certification requirements

### 4.0.0
* Initial Release
