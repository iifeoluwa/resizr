<?php

namespace App\Constants;

/**
* 
*/
class Messages
{

	const UNABLE_TO_UPLOAD_IMAGE = 'A fatal error occurred while uploading the image.';

	const DM_SEND_SUCCESS = 'Image successfuly resized and sent to user.';
	
	const DM_SEND_FAILURE = 'Unable to send image to user. Sending failed.';

	const STARTING_TRANSFORMATION = 'Begin init proceess for the twitter user';

	const CHECK_DM = 'Making DB query to confirm that event hasnt been proceessed already';

}