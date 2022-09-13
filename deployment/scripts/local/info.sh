theIP=$(minikube ip)
bold=$(tput bold)
normal=$(tput sgr0)

echo Local environment created successfully. 
echo You should be able to access the web pod at ${bold}"http://$theIP:32082"${normal}, ${bold}"http://$theIP"${normal} or ${bold}"http://fa.minikube"${normal} .
echo Pods may take some time to finish creating

