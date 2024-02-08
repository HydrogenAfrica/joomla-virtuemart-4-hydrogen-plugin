# Hydrogen Joomla Virtuemart 4x Plugin

## Introduction

The Hydrogen Payment Gateway enables you to process payments using cards and account transfers for faster delivery of goods and services on your Joomla site, utilizing our Joomla extensions.

To get started, you must find the right Hydrogen ecommerce extension for your Joomla site. Currently, we have extensions for Virtuemart.

## Requirements

- Joomla installation
- Virtue Mart 4x Installation
- Hydrogen Account Setup (Auth Token)
- Compatibility PHP 8.1 / 8.x, Joomla 3/4/5
- Requires at least: Virtue Mart 3x and Joomla 3
- Tested up to Virtue Mart 4.3.2.0 and Joomla 4x
- License: GPLv2 or later

Sign up for a live and test account [here](https://dashboard.hydrogenpay.com/signup).

## Instalation: Installing the Plugin

### Step 1:
To get started, please follow [this link]() to the Hydrogen Joomla Virtuemart plugin on GitHub. Click on the "Clone or Download" button and then click the "Download Zip" button in the pop-out.

![Github Repo](img/github_repo.png)

### Step 2:
Next, go to your Joomla Dashboard >> Extensions >> Manage >> Install. On the Install page tab, select "Upload Package File" and upload the downloaded zip file. This would install and configure the plugin.

### Step 3:
Install Virtuemart before the Hydrogen Joomla Virtuemart plugin. Please ensure you have installed Virtuemart on your Joomla site before installing the Hydrogen Joomla Virtuemart plugin. The Hydrogen Joomla Virtuemart plugin cannot work without Virtuemart.

### Note:
VirtueMart is an open-source e-commerce extension designed for Joomla, a popular content management system (CMS). It allows website owners to transform their Joomla-powered websites into fully functional online stores.

Here's a brief overview of VirtueMart's key features and why it's often installed before integrating a payment gateway:

#### Features of VirtueMart:
- User-Friendly Interface
- Product Management
- Flexible Configuration
- Multilingual and Multi-currency Support
- Secure and Stable

### Step 4:
Configure Hydrogen Payment Gateway on Virtuemart. To set up the Hydrogen Payment Gateway, on your Joomla Settings, click on Virtuemart and select Payment Methods.

On the page that opens, you'll see the list of Payment methods available on your Virtuemart Plugin. To add Hydrogen Payment Gateway, click on the New button at the top and fill out the form that follows.

Below are the fields that you need to fill in the form:
- Payment Name: Simply fill in the name "Hydrogen"
- Set Alias: Also enter "Hydrogen"
- Published: Set to Yes
- Payment Description: This is the text that describes this Payment option to the user on checkout. You can just enter "Pay with your Debit/Credit Card"
- Payment Method: Click on the dropdown and select VM Payment - Hydrogen from the options.
- Currency: Select Naira from the list in the dropdown or the required currency

After that click on Save on the top of the page. When the page saves, click on the Configuration tab. It will open the configuration page where you will be required to enter your Auth Token. You must save the Payment Method Information before clicking on the Configuration tab. If you don't do that, you will not see the Auth Token form.

### Step 5:
Please go to your Hydrogen Dashboard >> Settings >> Auth Token, copy your Auth Token, and fill them in the appropriate fields.

### How to get your Test and Live Auth Token:
There are 2 states on your dashboard: Live Mode and Test Mode. You'll see the Test Mode/Live Mode toggle on the top right corner of your dashboard. If there is no toggle and it's just Test Mode, this means that your Hydrogen account has not been activated.

When you go to the Settings Page to get your Auth Token, please note the mode that your dashboard is in, as that will determine the tokens that will be displayed. So if the dashboard is on Test Mode, you can only see the Test Auth Token and vice versa. To see the other token, switch the toggle from one mode to another.

At the top of this tab is the Test Mode dropdown. Hydrogen provides test parameters that allow you to simulate a transaction without using real money. If you select Yes, Hydrogen will be using your Test Auth Token to parse the payments, meaning that the orders processed then will be done with test cards, no real money is exchanged, therefore, no real value should be delivered.

If you do these things correctly, you should see Hydrogen in the list of payment options on checkout.

## Plugin Features

- Accept payment via Mastercard, Visa, Verve, and Bank Accounts
- Seamless integration into the VirtueMart checkout page. Accept payment directly on your site
