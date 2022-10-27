<?php

//----------------------------------\
//                                  |
//     Essential utilities          |
//                                  |
//----------------------------------/

function requiredField(string $fieldName, int $errorCode=8){
    return $_REQUEST[$fieldName] ?? returnResult("Field '$fieldName' not specified.", $errorCode);
}

function optionalField(string $fieldName, $defaultValue=''){
    return $_REQUEST[$fieldName] ?? $defaultValue;
}

// exits script giving exitCode and result
function returnResult($result, int $exitCode=0){
    $tmp = array();

    $tmp['exitCode'] = $exitCode;
    $tmp[ 'result' ] = $result;

    exit( json_encode( $tmp ) );
}