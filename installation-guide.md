Prerequisite
------------

Php extension:

-   Soap

Installation 
------------

We can only guarantee that the modules work on woocommerce version 2.1.5-2.3.\*

Note: Before you update to a newer version of WooCommerce, always make a backup as we don’t guarantee functionality of new versions of Woocommerce

We can only guarantee that the modules work in the standard theme and checkout of woocommerce.

NOTE! If you are using modules under 2.0 of this module (released aug 2015) you first need to remove all old PayEx modules in the plugin folder of your Wordpress installation, usually /wp-content/plugins

Then sign in as administrator on your wordpress site, click the plugins menu item, then “add new”

![image1](https://cloud.githubusercontent.com/assets/12283/12843856/573de27e-cbfc-11e5-84e5-76ed687d9618.png)

then “upload”. Browse and find the module you want to upload and then click install.

![image2](https://cloud.githubusercontent.com/assets/12283/12843871/5fad651a-cbfc-11e5-9cb9-9d564f9fa09c.jpeg)

When the module have uploaded successfully then you can activate it.

![image3](https://cloud.githubusercontent.com/assets/12283/12843874/5faf63ba-cbfc-11e5-88df-de137ad915dc.jpeg)

Done

You can also install the modules using ftp/sftp:

Unzip the modules on your computer and transfer the folders over to the plugins folder which should be in root/wp-content/plugins/ of your site using FTP/SFTP, the root is where all your wordpress files are.

Then log on to your wordpress site as admin and go to the plugins menu and click activate on all the modules you installed.

Configuration
-------------

To configure the modules you have to go to the menu called WooCommerce and click settings.

![image4](https://cloud.githubusercontent.com/assets/12283/12843872/5fae264e-cbfc-11e5-85c0-ffc5a2bb4509.jpeg)

Then in the overhead menu you click checkout

![image5](https://cloud.githubusercontent.com/assets/12283/12843873/5faeb1f4-cbfc-11e5-8b7f-79aff1852dff.jpeg)

and there you can choose which module you want to configure.

![image6](https://cloud.githubusercontent.com/assets/12283/12843875/5fafe0ce-cbfc-11e5-9e00-7fcbb88834dc.jpeg)

PayEx Bank Debit
----------------

![image7](https://cloud.githubusercontent.com/assets/12283/12843876/5fb09f1e-cbfc-11e5-9e02-cd404438cd01.png)

Enable/disable: Check the box to enable the plugin

Title: Title of plugin as the user will see it

Description: This controls the title which the user sees during checkout

Account number: You can collect the account number in Payex Merchant Admin; for production mode: https://secure.payex.com/Admin/Logon.aspx and for Test mode: http://test-secure.payex.com/Admin/Logon.aspx Remember there are different account numbers for test and production mode. For more info contact PayEx support.solutions@payex.com

<span id="_Toc393283744" class="anchor"></span>**Encryption key**: The encryption key you get in Payex Merchant Admin (Choose md5). Remember there are different encryption keys for test and production mode For more information contact PayEx support <support.solutions@payex.com>.

How to generate an encryption key

Step 1:

you must go to http://www.payexpim.com/ and choose admin for either test or production environment. ![image8](https://cloud.githubusercontent.com/assets/12283/12843880/5fc8c080-cbfc-11e5-9ee4-8e3def333754.png)

Step 2:

Sign in with the information you have been given by payex

![image9](https://cloud.githubusercontent.com/assets/12283/12843877/5fc7cc0c-cbfc-11e5-8cd5-b04829af760c.png)

Step 3: In the margin on the left, find “Merchant” and click on “Merchant profile”

![image10](https://cloud.githubusercontent.com/assets/12283/12843881/5fc9b116-cbfc-11e5-9a42-80cf8ffa3ef2.png)

Step 4:

Click on “new encryption key”

![image11](https://cloud.githubusercontent.com/assets/12283/12843878/5fc82b8e-cbfc-11e5-81b4-d8d834b86ad0.png)

Complete

Banks: click to find the selection of banks that work with this module.

Language: The language that the module will display to the user

Test mode: check this box if you want the module to run in test mode

Debug: check this to get debug logs

PayEx Factoring and part payment
--------------------------------

![image12](https://cloud.githubusercontent.com/assets/12283/12843882/5fc9adf6-cbfc-11e5-8f9f-1f1309e8ec64.png)

Enable/disable: Check the box to enable the plugin

Title: Title of plugin as the user will see it

Description: This controls the title which the user sees during checkout

Account number: You can collect the account number in Payex Merchant Admin; for production mode: https://secure.payex.com/Admin/Logon.aspx and for Test mode: http://test-secure.payex.com/Admin/Logon.aspx Remember there are different account numbers for test and production mode. For more info contact PayEx support.solutions@payex.com

Encryption key: Ctrl+click [here](#encry) to see a guide on how to generate an encryption key

Payment type: You can choose between factoring, part payment or user select. On user select the customer will choose either factoring or part payment in the checkout.

Language: The language that the module will display to the user

Test mode: check this box if you want the module to run in test mode

Debug: check this to get debug logs

Fee: set a factoring fee, set 0 to disable

GetAddress
----------

Get address is a supplementary module you can get that only works with the factoring module. What it does is when you enter your social security number if automatically finds and enters your address in the form for you.

PayEx Payments
--------------

![image13](https://cloud.githubusercontent.com/assets/12283/12843879/5fc85636-cbfc-11e5-9d5e-2bc861dd2ebf.png)

Enable/disable: Check the box to enable the plugin

Title: Title of plugin as the user will see it

Description: This controls the title which the user sees during checkout

Account number: You can collect the account number in Payex Merchant Admin; for production mode: https://secure.payex.com/Admin/Logon.aspx and for Test mode: http://test-secure.payex.com/Admin/Logon.aspx Remember there are different account numbers for test and production mode. For more info contact PayEx support.solutions@payex.com

Encryption key: Ctrl+click [here](#encry) to see a guide on how to generate an encryption key

Purchase operation: If AUTHORIZATION is submitted, this indicates that the order will be a 2-phased transaction if the payment method supports it. SALE is a 1-phased transaction.

Payment view: Which type of payment model you would like to use

Language: The language that the module will display to the user

Test mode: check this box if you want the module to run in test mode

Debug: check this to get debug logs

PayEx autopay
-------------

![image14](https://cloud.githubusercontent.com/assets/12283/12843884/5fe05f24-cbfc-11e5-8b31-9bf06ad4a061.png)

Enable/disable: Check the box to enable the plugin

Title: Title of plugin as the user will see it

Description: This controls the title which the user sees during checkout

Account number: You can collect the account number in Payex Merchant Admin; for production mode: https://secure.payex.com/Admin/Logon.aspx and for Test mode: http://test-secure.payex.com/Admin/Logon.aspx Remember there are different account numbers for test and production mode. For more info contact PayEx support.solutions@payex.com

<span id="encry" class="anchor"></span>Encryption key: Ctrl+click [here](#encry) to see a guide on how to generate an encryption key

Purchase operation: If AUTHORIZATION is submitted, this indicates that the order will be a 2-phased transaction if the payment method supports it. SALE is a 1-phased transaction.

Language: The language that the module will display to the user

Max amount: A limit on how much a order can have

Agreement URL: url to the user/payment agreement

Test mode: check this box if you want the module to run in test mode

Debug: check this to get debug logs

Translating the modules to other languages
------------------------------------------

To translate the modules you need a program called poedit. The files you need to translate are located in wp-content/plugins/woocommerce-gateway-payex/languages/

Every module have their own pot file that will be used to translate in to your desired language.

Simply open up the .pot file with poedit and select the line which you want to translate and in the bottom row add the translation. ![image15](https://cloud.githubusercontent.com/assets/12283/12843885/5fe2bf26-cbfc-11e5-9302-04178ae8e350.jpeg) The translation will appear in the right column of the main window.

When you’re done don’t forget to set the language in the top right to the language you just translated to.

Important: when you’re done, save the file as a .po file. And the file needs to be named with the language code of your country. So for example if you translated the woocommerce-gateway-payex module to german then the file should be named payex-de\_DE

You can find the language code of your country can be found here: <http://www.lingoes.net/en/translator/langcode.htm>

To change to the language that you just translated to you just need open wp-config and change the language to the translated one, IE for german its should say:

define('WPLANG', 'de\_DE');

How to activate Transaction Callback
------------------------------------

Transaction callback is an extra process used by PayEx to verify that the webshop is informed of the result of the payment processing. It is useful if your server goes down during payment or if customer close the webbrowser or lose connection just after payment. Callback is a required functionality.

![image16](https://cloud.githubusercontent.com/assets/12283/12843883/5fdf39c8-cbfc-11e5-9064-c56a4b95f0ca.jpg)

Use the following URL

<http://siteurl.net/?wc-api=WC_Gateway_Payex>

Change siteurl.net for your shop's url

Troubleshooting
---------------

There have been cases where the total cost of a product in the cart and the total in the payex payment view isn’t the same. That would be because of woocommerce rounding the total up or down because of lack of decimals. To fix this you have to add decimals to your products cost. To do this you login as admin, click woocommerce and then settings. There under general look for “Number of decimals” and set them to 2 and then save the changes.
