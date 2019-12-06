<?php

namespace App\Http\Controllers\API;
use Illuminate\Http\Request; 
use Illuminate\Support\Facades\Auth; 
use App\Http\Controllers\Controller; 
use App\User; 
use App\PasswordReset;
use App\Notifications\PasswordResetRequest;
use App\Notifications\PasswordResetSuccess;
use Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;


class UserController extends Controller 
{
    public $successStatus = 200;
    
    // User Register api
    public function register(Request $request) { 
        // Validate requested data
        $validator = Validator::make($request->all(), [ 
            'name' => 'required', 
            'email' => 'required|email|unique:users', 
            'password' => 'required', 
            'c_password' => 'required|same:password' 
        ]);
        
        if ($validator->fails()) { 
            // Return Error Message
            $message = "User email already register.";
            $error = $validator->errors();
            return $this->resError ($message,401,$error);
        }

        $input = $request->all(); 
        $input['password'] = bcrypt($input['password']); 
        $user = User::create($input); 
        
        // Success Message
        $userInfo = [
            'userId' => (isset($user->id) && !empty ($user->id)) ? $user->id: "",
            'userName' => (isset($user->name) && !empty ($user->name)) ? $user->name: "",
            'userEmail' => (isset($user->email) && !empty ($user->email)) ? $user->email: "",
            'userEmailVerified' => (isset($user->email_verified_at) && !empty ($user->email_verified_at)) ? $user->email_verified_at: "",
            'createdAt' => (isset($user->created_at) && !empty ($user->created_at)) ? $user->created_at->format('Y-m-d H:i:s A'): "",
            'updatedAt' => (isset($user->updated_at) && !empty ($user->updated_at)) ? $user->updated_at->format('Y-m-d H:i:s A'): "",
            'userToken' => $user->createToken('MyApp')-> accessToken
        ];

        // Return Success Response
        $message = "User registered successfully.";
        return $this->resSuccess ($message, $userInfo);
    }

    // User Login API
    public function login() { 
        if (Auth::attempt(['email' => request('email'), 'password' => request('password')])) { 
            // Create User Access Token 
            $user  = Auth::user(); 
            $token =  $user->createToken('MyApp')-> accessToken; 
            
            // Get Login User Details
            $userInfo = $this->getLoginUserDetails();
            $userInfo['userToken']  = $token;

            // Return Success Response
            $message = "User login successfully.";
            return $this->resSuccess ($message, $userInfo);
        } else { 
            // Return Error Response
            $message = "Unauthorised user.";
            return $this->resError ($message,401);
        } 
    }
	
	// User Social Login API
    public function socialLogin (Request $request) { 
		// Validate requested data
        $validator = Validator::make($request->all(), [ 
            'social_media_token' => 'required'
        ]);
        
        if ($validator->fails()) { 
            // Return Error Message
            $message = "Social media token is required.";
            $error = $validator->errors();
            return $this->resError ($message,401,$error);
        }

		$email = (!empty (request('email'))) ? request('email'): request('social_media_token')."@tmpEmail.com";
		$name  = (!empty (request('name'))) ? request('name'): "N/A";
		$password = "!@#$%";
		
		$findUserByEmail = User::where('email', $email)->get(['id']);
		$isSocialMediaExist = (count ($findUserByEmail)) ? true : false;        

		if (!$isSocialMediaExist) {
			$input['name'] = $name;
			$input['email'] = $email;
			$input['social_media_token'] = request('social_media_token');
			$input['password'] = bcrypt($password); 
			User::create($input);
		} 
				
		if(Auth::attempt(['email' => $email, 'password' => $password])) { 
            // Create User Access Token 
            $user  = Auth::user(); 
            $token =  $user->createToken('MyApp')-> accessToken; 
            
            // Get Login User Details
            $userInfo = $this->getLoginUserDetails();
            $userInfo['userToken']  = $token;

            // Return Success Response
            $message = "User login successfully.";
            return $this->resSuccess ($message, $userInfo);
        } else { 
            // Return Error Response
            $message = "Unauthorised user.";
            return $this->resError ($message,401);
        }
		 
    }    

    // Send Forgot Password Link  
    public function send_forgot_password_link(Request $request) {
        // Validate requested data
        $validator = Validator::make($request->all(), [ 
           'email' => 'required|email' 
        ]);
       
        if ($validator->fails()) { 
           // Return Error Message
           $message = "Email is required.";
           $error = $validator->errors();
           return $this->resError ($message,401,$error);
        }

        $user = User::where('email', $request->email)->first();

        if (empty ($user)) {
            // Return Success Response
            $message = "Email is wrong. Please enter registered email.";
            return $this->resError ($message,404);
        }        

        $passwordReset = PasswordReset::updateOrCreate(
           ['email' => $user->email],
           [
               'email' => $user->email,
               'token' => rand(5,60)
           ]
        );

        if ($user && $passwordReset) {
           $user->notify(
               new PasswordResetRequest($passwordReset->token)
           );
        }
       
        $message = "Forgot password link has been send to your registered email id.";
        return $this->resSuccess ($message);
    }

    // Check forgot password link
    public function find_forgot_password_token($token) {
        $passwordReset = PasswordReset::where('token', $token)->first();

        if (!$passwordReset) {
            $message = "This password reset token is invalid.";
            return $this->resError ($message,404);
        }
           
        if (Carbon::parse($passwordReset->updated_at)->addMinutes(720)->isPast()) {
            $passwordReset->delete();
            $message = "This password reset token is invalid. Token expired.";
            return $this->resError ($message,404);
        }

        $message = "Token is valid.";
        return $this->resSuccess ($message,$passwordReset);
    }

    //  Reset Password
    public function reset_forgot_password(Request $request) {
        // Validate requested data
        $validator = Validator::make($request->all(), [ 
            'email' => 'required|string|email',
            'password' => 'required|string',
            'token' => 'required|string',
            'c_password' => 'required|same:password',
        ]);
        
        if ($validator->fails()) { 
            // Return Error Message
            $message = "All fields are required.";
            $error = $validator->errors();
            return $this->resError ($message,401,$error);
        }
        
        $passwordReset = PasswordReset::where([
            ['token', $request->token],
            ['email', $request->email]
        ])->first();

        if (empty ($passwordReset)) {
            // Return Error Message
            $message = "This password reset token is invalid.";
            return $this->resError ($message,404);
        } 

        $user = User::where('email', $passwordReset->email)->first();
        if (empty ($user)) {
            // Return Error Message
            $message = "We cant find a user with that e-mail address.";
            return $this->resError ($message,404);
        }
          
        $user->password = bcrypt($request->password);
        $user->save();
        $passwordReset->delete();
        $user->notify(new PasswordResetSuccess($passwordReset));

        $message = "Password reset successfully.";
        return $this->resSuccess ($message,$user);
    }
   
    // Update User Password API
    public function updateAdminPassword(Request $request) { 
        // Validate requested data
        $validator = Validator::make($request->all(), [ 
            'user_id' => 'required',
            'current_password' => 'required', 
            'new_password' => 'required', 
            'confirm_password' => 'required'
        ]);
        
        $input = $request->all(); 

        if ($input['new_password'] != $input['confirm_password']) {
            $message = "The confirm password and new password must match.";
            return $this->resError ($message,401);
        }

        if ($validator->fails()) { 
            // Return Error Message
            $message = "All fields are required.";
            $error = $validator->errors();
            return $this->resError ($message,401,$error);
        }
            
        $userInfo = User::where('id', $input['user_id']) -> first();

        if (!empty ($userInfo)) {     
            // Check Current Password with database hash password
            if (Hash::check ($input['current_password'] , $userInfo->password)) {
                // Update Password
                
                $data =  User::updateOrCreate(['id' => $userInfo->id], ['password' => bcrypt($input['confirm_password'])]);

                $message = "Your password has been updated successfully.";
                return $this->resSuccess ($message);

            } else {
                $message = "Old password is not matching.";
                return $this->resError ($message,401);
            }           
        } else {
            $message = "Error: Server error.";
            return $this->resError ($message,401);
        }
    }
        
    // User Profile API 
    public function userProfile() { 
        $userInfo = $this->getLoginUserDetails();

        $response = [
            'success'=> true,
            'message'=> "",
            'data'   => $userInfo
        ];
       
        return response()->json($response, $this-> successStatus); 
    } 

    private function getLoginUserDetails () {
        $user = Auth::user(); 
        $userInfo = [
            'userId' => (isset($user->id) && !empty ($user->id)) ? $user->id: "",
            'userName' => (isset($user->name) && !empty ($user->name)) ? $user->name: "",
            'userEmail' => (isset($user->email) && !empty ($user->email)) ? $user->email: "",
            'userEmailVerified' => (isset($user->email_verified_at) && !empty ($user->email_verified_at)) ? $user->email_verified_at: "",
            'createdAt' => (isset($user->created_at) && !empty ($user->created_at)) ? $user->created_at->format('Y-m-d H:i:s A'): "",
            'updatedAt' => (isset($user->updated_at) && !empty ($user->updated_at)) ? $user->updated_at->format('Y-m-d H:i:s A'): "",
        ];

        return $userInfo;
    }

    private function resSuccess ($message = "", $data = []) {
        $message = empty($message) ? "" : $message;
        $data    = empty($data) ? (object)($data) : $data;

        $response = [
            'success'=> true,
            'message'=> $message,
            'data'   => $data
        ];
       
        return response()->json($response, $this-> successStatus); 
    }

    private function resError ($message = "", $statusCode = 401, $error = []) {
        $message    = empty($message) ? "" : $message;
        $error      = empty($error) ? (object)($error) : $error;
        $statusCode = empty($statusCode) ? 401 : $statusCode;

        $response = [
            'success'=> false,
            'message'=> $message,
            'error'=> $error
        ];
        return response()->json($response, $statusCode); 
    }   

}