const aws = require('aws-sdk');

const argv = process.argv.slice(2);

if (argv.length != 3) {
    console.error("usage: node ./script/presign-upload-url.js <region> <bucket-name> <filename>");
    process.exit(1);
}

const region = argv[0];
const bucket = argv[1];
const file = argv[2];

aws.config = {
    region: region,
    signatureVersion: 'v4', // it does matter for some regions
};

const s3 = new aws.S3();
const sign = s3.getSignedUrl('putObject', {
    Bucket: bucket,
    Expires: 60 * 60,
    Key: file,
});

console.log(sign);
