# WooCommerce ChatGPT Assistant Plugin

This plugin adds a simple chatbox inside the WordPress admin that connects to the OpenAI API. Speak to the assistant in natural language and it can carry out WooCommerce tasks like adding products.

## Installation

1. Copy the `wc-chatgpt-assistant` directory to your WordPress `plugins` folder.
2. Activate **WooCommerce ChatGPT Assistant** from the WordPress plugins screen.
3. Visit **Woo Chat Assistant > Settings** and provide your OpenAI API key.
4. Open **Woo Chat Assistant** in the admin menu to chat with the assistant.

## Usage

Interact with the chatbox using normal sentences (for example "Ajoute un produit Test à 9.99 €"). The assistant will answer and may include a small JSON block describing the task. When a block like this appears:

```json
{"command": "add_product", "name": "Product Name", "price": 19.99}
```

a new WooCommerce product will be created automatically.
