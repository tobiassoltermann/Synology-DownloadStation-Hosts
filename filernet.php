<?php
	/**
	 *	Retrieves download information about a file from filer.net. This class implements the interface to Synology's DownloadStation
	 *	More information about this can be found here: http://www.synology.com/de-de/support/guide_download_station
	 *	This file is useless without a corresponding INFO file.
	 */
	class SynoFileHostingFilernet
	{
		private $Url;
		private $Username;
		private $Password;
		private $HostInfo;
		private $file;

		public function __construct($Url, $Username, $Password, $HostInfo)
		{
			$this->Url = $Url;
			$this->Username = $Username;
			$this->Password = $Password;
			$this->HostInfo = $HostInfo;
		}

		/**
		 *	Gets the download info as requested by Synology DownloadStation
		 *	@return	mixed The associative array containing download information
		 */
		public function GetDownloadInfo()
		{
			// Find the file hash and transform URL into an API-URL
			preg_match("/http(.?):\/\/filer.net\/get\/(.*)$/", $this->Url, $output);
			$newUrl = trim("http://api.filer.net/dl/".$output[2].".json");

			// The API response redirects twice before return the real download URL
			$redirectedUrl = $this->getRedirectUrl($newUrl);
			$redirectedUrl2 = $this->getRedirectUrl($redirectedUrl);

			// Setting return information
			$DownloadInfo[DOWNLOAD_ISPARALLELDOWNLOAD] = TRUE;
			$DownloadInfo[DOWNLOAD_ISQUERYAGAIN] = 2;
			$DownloadInfo[DOWNLOAD_URL] = $redirectedUrl2;

			// Find the download name, splitting the download URL and look for the filename part
			$splitUrl = explode("/", $redirectedUrl2);
			$DownloadInfo[DOWNLOAD_FILENAME] = $splitUrl[count($splitUrl)-1];
			return $DownloadInfo;
		}

		/**
		 *	Finds the URL that we're being redirected to
		 *	@param	string	url	The URL we want to analyze
		 *	@return	string	The destination URL to which the redirect leads
		 */
		private function getRedirectUrl($url)
		{
			// Initialize CURL
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, "$url");
			// We want the answer in a string
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			// Transfer authentication across hosts, as the real download url might have another hostname
			curl_setopt($ch, CURLOPT_UNRESTRICTED_AUTH, TRUE);
			// Authentication takes place as "username:password" in CURL
			curl_setopt($ch, CURLOPT_USERPWD, $this->Username . ":" . $this->Password);

			// Execute the request and get meta information (redirect)
			$getOutput = curl_exec($ch);
			$curlInfo = curl_getinfo($ch);

			// The redirect url is returned
			$redirectUrl = $curlInfo["redirect_url"];
			curl_close($ch);
			return $redirectUrl;
		}

		/**
		 *  Execute query to profile API and return whether the user is free or premium
		 *  @param	boolean	ClearCookie	Whether the cookie should be cleared after login. We don't care about this
		 *	@return	int USER_IS_FREE if user is free, USER_IS_PREMIUM if it's a premium user, LOGIN_FAIL otherwise
		 */
		public function Verify($ClearCookie)
		{
			$hdl = fopen("/tmp/out.txt", "w");
			// Init Curl and set options
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, "http://api.filer.net/api/profile.json");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_USERPWD, $this->Username . ":" . $this->Password);
			$getOutput = curl_exec($ch);
			curl_close($ch);
			// Decode the JSON we have received
			$decodedJson = json_decode($getOutput, TRUE);
			$state = $decodedJson["data"]["state"];
			fwrite($hdl, "State: $state\n");
			switch ($state)
			{
				case "premium":
					fwrite($hdl, "Return USER_IS_PREMIUM\n");
					fflush($hdl);
					return USER_IS_PREMIUM;
				case "free":
					fwrite($hdl, "Return USER_IS_FREE\n");
					fflush($hdl);
					return USER_IS_FREE;
			}
			// If the result wasn't JSON or if the state was another one, login failed
			fwrite($hdl, "Return LOGIN_FAIL\n");
			fflush($hdl);
			return LOGIN_FAIL;
		}
	}
?>
