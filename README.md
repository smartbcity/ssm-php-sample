# PHP sample: Token management



# SSM Sample script

---

The ssm-sample PHP script provides examples to interact with the SmartB blockchain. 

While offering a lot of examples, the use case focuses on basic interactions with an "account" state machine registering the balance of tokens.

The main interactions to focus on at first are: 

- the creation of a new account
- crediting an account
- debiting an account
- display of the current state of an account

More advanced examples are also available:

- create a new state machine
- register new users
- perform transactions on a state machine
- various queries
- the analysis of the different functions provides additional insight





# Before Starting

The PHP script uses a number of RSA key pairs that have been registered on the test server. In order for the script to work with its current configuration, the private keys of the users `bank`and `shop`have to be present in the directory. The private key of the user`ssm-admin`is also required to create new accounts (new SSM sessions to be precise). If you don't have them, please contact SmartB to get the `keys` folder.



# Account Creation (Start the automaton)

---

In this example each new account will be managed on a new session of the `account` SSM. For each new session, a new initial state is required. The initial state specifies, the name of the session and the roles.

```php
// Load the private key required to complete the operation
$privateKey = openssl_pkey_get_private("file://<admin-private-key-file>");

$initialState = array(
  "ssm"     => "account",
  "session" => "account-5678",
  "roles"   => array(
      "bank"    => "Creditor",
      "shop"    => "Debitor"
  )
);

// Start the SSM with the invoke command
invoke("start", json_encode($initialState), "ssm-admin", $privateKey)
```



# Credit Account

---

```php
//-------------------------------------------------------
// credit: wrapper around the perform function
//      add the specified $amount to the $token balance
//-------------------------------------------------------
// 
// credit(
//    <account-id>,   // ssm session 
//    <token>,
//    <amount>, 
//    <credito-name>, 
//    <path-to-creditor-priv-key>
// )

credit("account-5678", "vert", 1, "bank", "file://bank");
```



# Debit Account

---

```php
//-------------------------------------------------------
// debit: wrapper around the perform function
//      decuced the specified $amount from $token 
//-------------------------------------------------------
// 
// debit(
//    <account-id>,   // ssm session 
//    <token>, 
//    <amount>, 
//    <credito-name>, 
//    <path-to-creditor-priv-key>
// )

debit("account-5678", "rouge", 4, "shop", "file://shop");
```



# Current State of an Account

The `getCurrentState()` runs a specific query and extracts the state to return it. 

More query examples are described in the code.

```php
// diplay current state with getCurrentState
//
// Example: the command below will return the balance for token "bleu"
// getCurrentState($accountName)["public"]["bleu"];

// Getting the complete state
echo jscon_encode(getCurrentState("account-5678"), JSON_PRETTY_PRINT);
```

output

```json
{
    "docType": "state",
    "ssm": "account",
    "session": "account-5678",
    "iteration": 23,
    "roles": {
        "bank": "Creditor",
        "shop": "Debitor"
    },
    "current": 0,
    "origin": {
        "from": 0,
        "to": 0,
        "role": "Debitor",
        "action": "Debit"
    },
    "public": {
        "jaune": 34,
        "noir": -3,
        "rouge": -16,
        "vert": 14
    }
}
```