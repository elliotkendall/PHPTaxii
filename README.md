# PHPTaxii
A PHP Class to interface with TAXII feeds

This is a class that allows you to perform simple operations on a TAXII
server.  It is based on TAXIIExample.py from Soltra Edge.

## Initialization

You can instantiate the class with a URL, and either a username/password
pair or a PEM file containing a client certificate. For example,

    $taxii = new TAXIIClient('http://your-url-here', 'username', 'password');

or

    $taxii = new TAXIIClient('http://your-url-here', NULL, NULL, '/path/to/clientcert.pem', 'optional pem password');

## Discovery

To send a Discovery Request, call discover() with no arguments. It returns
results as an array.  For example,

    print_r($taxii->discover());

might display (some fields removed for readability)

    Array
    (
        [0] => Array
            (
                [type] => DISCOVERY
                [address] => https://some-taxii-server/taxii-data
            )
    
        [1] => Array
            (
                [type] => COLLECTION_MANAGEMENT
                [address] => https://some-taxii-server/taxii-data
            )
    
        [2] => Array
            (
                [type] => POLL
                [address] => https://some-taxii-server/taxii-data
            )
    
        [3] => Array
            (
                [type] => INBOX
                [address] => https://some-taxii-server/taxii-data
            )
    )

## Listing Collections

To send a Collection Information Request, call getCollectionInfo() with no
arguments.  It returns results as an array.  For example,

    print_r($taxii->getCollectionInfo());

might display (some fields removed for readability)

    Array
    (
        [0] => Array
            (
                [name] => system.Default
                [type] => DATA_FEED
                [description] => system.Default
                [address] => https://some-taxii-server/taxii-data
            )
    
        [1] => Array
            (
                [name] => system.Test
                [type] => DATA_FEED
                [description] => system.Test
                [address] => https://some-taxii-server/taxii-data
            )
    
    )

## Polling Collections

To send a Poll Request for a collection, call poll() with the name of the
collection as shown in the output of getCollectionInfo.  It returns an array
with sub-arrays of IP addresses and domain names.  Other data types and
metadata are not currently supported. For example,

    print_r($taxii->poll('system.Default'));

might display

    Array
    (
        [dns] => Array
            (
                [0] => foo.com
                [1] => bar.com
                [2] => baz.com
    
            )
    
        [ip] => Array
            (
                [0] => 1.1.1.1
                [1] => 2.2.2.2
                [2] => 3.3.3.3
            )
    )

## Inbox Messages

To send an Inbox Message, call sendInboxMessage() with a string containing
the message to send.  It does not return anything.  This method is untested,
but really just sticks your message (including possible XML) into the
taxii_11:Content tag of a pre-defined XML message.
