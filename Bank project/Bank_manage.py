import pathlib
import os 
import pickle

class Account:
    accNo = 0
    name = ''
    deposit = 0
    type = ''

    def createaccount(self):
        self.accNo = int(input("Enter the account no.\n"))
        self.name = input("enter your name:\n")
        self.type = input("enter the type of account [C/S]:\n")
        self.deposit = int(input("Enter the amount (>=500 for saving and >=1000 for current)"))
        print("\n \n Acoount Created" )